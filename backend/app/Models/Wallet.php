<?php
namespace App\Models;

use Core\Model;
use Core\Database;

class Wallet extends Model
{
    protected string $table = 'wallets';

    /**
     * 根据用户ID获取钱包
     */
    public function findByUserId(int $userId): ?array
    {
        return $this->findBy(['user_id' => $userId]);
    }

    /**
     * 获取或创建钱包
     */
    public function getOrCreate(int $userId): array
    {
        $wallet = $this->findByUserId($userId);

        if (!$wallet) {
            $id = $this->create(['user_id' => $userId]);
            $wallet = $this->find($id);
        }

        return $wallet;
    }

    /**
     * 充值（带事务）
     */
    public function recharge(int $userId, float $amount, string $description = '账户充值'): bool
    {
        return $this->db->transaction(function($db) use ($userId, $amount, $description) {
            $wallet = $this->getOrCreate($userId);

            // 更新余额（乐观锁）
            $sql = "UPDATE wallets SET
                    balance = balance + ?,
                    total_recharge = total_recharge + ?,
                    version = version + 1
                    WHERE id = ? AND version = ?";

            $stmt = $db->query($sql, [$amount, $amount, $wallet['id'], $wallet['version']]);

            if ($stmt->rowCount() === 0) {
                throw new \RuntimeException('操作失败，请重试');
            }

            // 记录流水
            $transModel = new Transaction();
            $transModel->record(
                $userId,
                $wallet['id'],
                Transaction::TYPE_RECHARGE,
                $amount,
                $wallet['balance'],
                $wallet['balance'] + $amount,
                null,
                null,
                $description
            );

            return true;
        });
    }

    /**
     * 冻结金额
     */
    public function freeze(int $userId, float $amount, int $relatedId, string $relatedType, string $description): bool
    {
        return $this->db->transaction(function($db) use ($userId, $amount, $relatedId, $relatedType, $description) {
            $wallet = $this->getOrCreate($userId);

            if ($wallet['balance'] < $amount) {
                throw new \RuntimeException('账户余额不足，请先充值');
            }

            // 更新余额
            $sql = "UPDATE wallets SET
                    balance = balance - ?,
                    frozen_amount = frozen_amount + ?,
                    version = version + 1
                    WHERE id = ? AND version = ? AND balance >= ?";

            $stmt = $db->query($sql, [$amount, $amount, $wallet['id'], $wallet['version'], $amount]);

            if ($stmt->rowCount() === 0) {
                throw new \RuntimeException('操作失败，请重试');
            }

            // 记录流水
            $transModel = new Transaction();
            $transModel->record(
                $userId,
                $wallet['id'],
                Transaction::TYPE_FREEZE,
                $amount,
                $wallet['balance'],
                $wallet['balance'] - $amount,
                $relatedId,
                $relatedType,
                $description,
                $wallet['frozen_amount'],
                $wallet['frozen_amount'] + $amount
            );

            return true;
        });
    }

    /**
     * 解冻金额
     */
    public function unfreeze(int $userId, float $amount, int $relatedId, string $relatedType, string $description): bool
    {
        return $this->db->transaction(function($db) use ($userId, $amount, $relatedId, $relatedType, $description) {
            $wallet = $this->getOrCreate($userId);

            if ($wallet['frozen_amount'] < $amount) {
                throw new \RuntimeException('冻结金额不足');
            }

            // 更新余额
            $sql = "UPDATE wallets SET
                    balance = balance + ?,
                    frozen_amount = frozen_amount - ?,
                    version = version + 1
                    WHERE id = ? AND version = ? AND frozen_amount >= ?";

            $stmt = $db->query($sql, [$amount, $amount, $wallet['id'], $wallet['version'], $amount]);

            if ($stmt->rowCount() === 0) {
                throw new \RuntimeException('操作失败，请重试');
            }

            // 记录流水
            $transModel = new Transaction();
            $transModel->record(
                $userId,
                $wallet['id'],
                Transaction::TYPE_UNFREEZE,
                $amount,
                $wallet['balance'],
                $wallet['balance'] + $amount,
                $relatedId,
                $relatedType,
                $description,
                $wallet['frozen_amount'],
                $wallet['frozen_amount'] - $amount
            );

            return true;
        });
    }

    /**
     * 结算收入（达人）
     */
    public function settle(int $userId, float $amount, int $relatedId, string $relatedType, string $description): bool
    {
        return $this->db->transaction(function($db) use ($userId, $amount, $relatedId, $relatedType, $description) {
            $wallet = $this->getOrCreate($userId);

            // 更新余额
            $sql = "UPDATE wallets SET
                    balance = balance + ?,
                    total_income = total_income + ?,
                    version = version + 1
                    WHERE id = ? AND version = ?";

            $stmt = $db->query($sql, [$amount, $amount, $wallet['id'], $wallet['version']]);

            if ($stmt->rowCount() === 0) {
                throw new \RuntimeException('操作失败，请重试');
            }

            // 记录流水
            $transModel = new Transaction();
            $transModel->record(
                $userId,
                $wallet['id'],
                Transaction::TYPE_SETTLE,
                $amount,
                $wallet['balance'],
                $wallet['balance'] + $amount,
                $relatedId,
                $relatedType,
                $description
            );

            return true;
        });
    }

    /**
     * 消费（从冻结金额扣除）
     */
    public function consume(int $userId, float $amount, int $relatedId, string $relatedType, string $description): bool
    {
        return $this->db->transaction(function($db) use ($userId, $amount, $relatedId, $relatedType, $description) {
            $wallet = $this->getOrCreate($userId);

            if ($wallet['frozen_amount'] < $amount) {
                throw new \RuntimeException('冻结金额不足');
            }

            // 更新余额
            $sql = "UPDATE wallets SET
                    frozen_amount = frozen_amount - ?,
                    total_expense = total_expense + ?,
                    version = version + 1
                    WHERE id = ? AND version = ? AND frozen_amount >= ?";

            $stmt = $db->query($sql, [$amount, $amount, $wallet['id'], $wallet['version'], $amount]);

            if ($stmt->rowCount() === 0) {
                throw new \RuntimeException('操作失败，请重试');
            }

            // 记录流水
            $transModel = new Transaction();
            $transModel->record(
                $userId,
                $wallet['id'],
                Transaction::TYPE_CONSUME,
                $amount,
                $wallet['balance'],
                $wallet['balance'],
                $relatedId,
                $relatedType,
                $description,
                $wallet['frozen_amount'],
                $wallet['frozen_amount'] - $amount
            );

            return true;
        });
    }
}
