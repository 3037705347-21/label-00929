<?php
namespace App\Services;

use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\Withdrawal;
use App\Models\SystemConfig;
use Core\Database;
use Core\Logger;

class WalletService
{
    private Wallet $walletModel;
    private Transaction $transModel;
    private Withdrawal $withdrawModel;
    private SystemConfig $configModel;

    public function __construct()
    {
        $this->walletModel = new Wallet();
        $this->transModel = new Transaction();
        $this->withdrawModel = new Withdrawal();
        $this->configModel = new SystemConfig();
    }

    /**
     * 获取钱包信息
     */
    public function getWallet(int $userId): array
    {
        return $this->walletModel->getOrCreate($userId);
    }

    /**
     * 充值
     */
    public function recharge(int $userId, float $amount): array
    {
        if ($amount <= 0) {
            throw new \RuntimeException('充值金额必须大于0');
        }

        // 这里应该调用第三方支付接口
        // 简化处理：直接增加余额
        $this->walletModel->recharge($userId, $amount);

        Logger::info('Wallet recharged', ['user_id' => $userId, 'amount' => $amount]);

        return $this->getWallet($userId);
    }

    /**
     * 申请提现
     */
    public function withdraw(int $userId, array $data): int
    {
        $wallet = $this->walletModel->getOrCreate($userId);

        $minAmount = $this->configModel->getValue('min_withdraw_amount', 100);
        $maxAmount = $this->configModel->getValue('max_withdraw_amount', 50000);
        $feeRate = $this->configModel->getValue('withdraw_fee_rate', 0.5);

        $amount = (float) $data['amount'];

        if ($amount < $minAmount) {
            throw new \RuntimeException("最低提现金额为{$minAmount}元");
        }

        if ($amount > $maxAmount) {
            throw new \RuntimeException("单次最高提现金额为{$maxAmount}元");
        }

        if ($wallet['balance'] < $amount) {
            throw new \RuntimeException('可提现余额不足');
        }

        // 计算手续费
        $fee = $amount * $feeRate / 100;
        $actualAmount = $amount - $fee;

        $db = Database::getInstance();

        return $db->transaction(function($db) use ($userId, $wallet, $amount, $fee, $actualAmount, $data) {
            // 冻结提现金额
            $sql = "UPDATE wallets SET
                    balance = balance - ?,
                    frozen_amount = frozen_amount + ?,
                    version = version + 1
                    WHERE id = ? AND version = ? AND balance >= ?";

            $stmt = $db->query($sql, [$amount, $amount, $wallet['id'], $wallet['version'], $amount]);

            if ($stmt->rowCount() === 0) {
                throw new \RuntimeException('操作失败，请重试');
            }

            // 创建提现申请
            $withdrawId = $this->withdrawModel->create([
                'withdraw_no' => $this->withdrawModel->generateWithdrawNo(),
                'user_id' => $userId,
                'amount' => $amount,
                'fee' => $fee,
                'actual_amount' => $actualAmount,
                'withdraw_type' => $data['withdraw_type'] ?? Withdrawal::TYPE_BANK,
                'account_name' => $data['account_name'],
                'account_no' => $data['account_no'],
                'bank_name' => $data['bank_name'] ?? null,
                'bank_branch' => $data['bank_branch'] ?? null,
                'status' => Withdrawal::STATUS_PENDING,
            ]);

            // 记录流水
            $this->transModel->record(
                $userId,
                $wallet['id'],
                Transaction::TYPE_FREEZE,
                $amount,
                $wallet['balance'],
                $wallet['balance'] - $amount,
                $withdrawId,
                'withdrawal',
                '提现申请冻结',
                $wallet['frozen_amount'],
                $wallet['frozen_amount'] + $amount
            );

            Logger::info('Withdrawal requested', ['user_id' => $userId, 'amount' => $amount, 'withdraw_id' => $withdrawId]);

            return $withdrawId;
        });
    }

    /**
     * 处理提现（管理员）
     */
    public function processWithdrawal(int $withdrawId, int $adminId, bool $approved, ?string $note = null): void
    {
        $withdrawal = $this->withdrawModel->find($withdrawId);

        if (!$withdrawal) {
            throw new \RuntimeException('提现申请不存在');
        }

        if ($withdrawal['status'] != Withdrawal::STATUS_PENDING) {
            throw new \RuntimeException('该申请已处理');
        }

        $db = Database::getInstance();

        $db->transaction(function($db) use ($withdrawal, $adminId, $approved, $note) {
            $wallet = $this->walletModel->findByUserId($withdrawal['user_id']);

            if ($approved) {
                // 审核通过 - 从冻结金额扣除
                $sql = "UPDATE wallets SET
                        frozen_amount = frozen_amount - ?,
                        total_withdraw = total_withdraw + ?,
                        version = version + 1
                        WHERE id = ? AND version = ?";

                $db->query($sql, [$withdrawal['amount'], $withdrawal['actual_amount'], $wallet['id'], $wallet['version']]);

                // 记录流水
                $this->transModel->record(
                    $withdrawal['user_id'],
                    $wallet['id'],
                    Transaction::TYPE_WITHDRAW,
                    $withdrawal['actual_amount'],
                    $wallet['balance'],
                    $wallet['balance'],
                    $withdrawal['id'],
                    'withdrawal',
                    '提现成功',
                    $wallet['frozen_amount'],
                    $wallet['frozen_amount'] - $withdrawal['amount']
                );

                // 记录手续费
                if ($withdrawal['fee'] > 0) {
                    $this->transModel->record(
                        $withdrawal['user_id'],
                        $wallet['id'],
                        Transaction::TYPE_PLATFORM_FEE,
                        $withdrawal['fee'],
                        $wallet['balance'],
                        $wallet['balance'],
                        $withdrawal['id'],
                        'withdrawal',
                        '提现手续费'
                    );
                }

                $this->withdrawModel->process($withdrawal['id'], Withdrawal::STATUS_PAID, $adminId, $note);
            } else {
                // 审核拒绝 - 解冻金额
                $sql = "UPDATE wallets SET
                        balance = balance + ?,
                        frozen_amount = frozen_amount - ?,
                        version = version + 1
                        WHERE id = ? AND version = ?";

                $db->query($sql, [$withdrawal['amount'], $withdrawal['amount'], $wallet['id'], $wallet['version']]);

                // 记录流水
                $this->transModel->record(
                    $withdrawal['user_id'],
                    $wallet['id'],
                    Transaction::TYPE_UNFREEZE,
                    $withdrawal['amount'],
                    $wallet['balance'],
                    $wallet['balance'] + $withdrawal['amount'],
                    $withdrawal['id'],
                    'withdrawal',
                    '提现申请被拒绝，金额已退回',
                    $wallet['frozen_amount'],
                    $wallet['frozen_amount'] - $withdrawal['amount']
                );

                $this->withdrawModel->process($withdrawal['id'], Withdrawal::STATUS_REJECTED, $adminId, $note);
            }
        });

        Logger::info('Withdrawal processed', ['withdraw_id' => $withdrawId, 'approved' => $approved]);
    }

    /**
     * 获取交易记录
     */
    public function getTransactions(int $userId, int $page, int $perPage, array $filters = []): array
    {
        return $this->transModel->getUserTransactions($userId, $page, $perPage, $filters);
    }

    /**
     * 获取提现记录
     */
    public function getWithdrawals(int $userId, int $page, int $perPage): array
    {
        return $this->withdrawModel->getUserWithdrawals($userId, $page, $perPage);
    }
}
