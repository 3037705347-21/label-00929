/**
 * 探店达人营销平台 - 公共JS
 */

// API 基础配置
const API_BASE = '/api';

// 获取当前端类型
function getPortalType() {
    const path = window.location.pathname;
    if (path.startsWith('/admin/')) return 'admin';
    if (path.startsWith('/merchant/')) return 'merchant';
    if (path.startsWith('/influencer/')) return 'influencer';
    return 'default';
}

// 获取Token
function getToken() {
    const portal = getPortalType();
    return localStorage.getItem(`token_${portal}`) || localStorage.getItem('token');
}

// 设置Token
function setToken(token) {
    const portal = getPortalType();
    localStorage.setItem(`token_${portal}`, token);
}

// 清除Token
function clearToken() {
    const portal = getPortalType();
    localStorage.removeItem(`token_${portal}`);
    localStorage.removeItem(`user_${portal}`);
    // 兼容旧版
    localStorage.removeItem('token');
    localStorage.removeItem('user');
}

// 获取用户信息
function getUser() {
    const portal = getPortalType();
    const user = localStorage.getItem(`user_${portal}`) || localStorage.getItem('user');
    return user ? JSON.parse(user) : null;
}

// 设置用户信息
function setUser(user) {
    const portal = getPortalType();
    localStorage.setItem(`user_${portal}`, JSON.stringify(user));
}

// 设置用户头像（通用函数）
function setUserAvatar(avatarEl, avatarUrl) {
    if (!avatarEl) return;
    if (avatarUrl) {
        avatarEl.innerHTML = `<img src="${avatarUrl}" alt="">`;
    } else {
        // 显示用户 emoji 作为默认头像
        avatarEl.textContent = '👤';
    }
}

// API 请求封装
async function request(url, options = {}) {
    const token = getToken();

    const config = {
        headers: {
            'Content-Type': 'application/json',
            ...options.headers,
        },
        ...options,
    };

    if (token) {
        config.headers['Authorization'] = `Bearer ${token}`;
    }

    if (config.body && typeof config.body === 'object') {
        config.body = JSON.stringify(config.body);
    }

    try {
        const response = await fetch(API_BASE + url, config);
        const data = await response.json();

        // 401 处理：登录接口返回401是正常的错误提示，不应跳转
        if (response.status === 401) {
            // 如果是登录/注册接口，直接返回数据让页面处理
            if (url.includes('/auth/login') || url.includes('/auth/register')) {
                return data;
            }
            // 其他接口401表示token失效，需要重新登录
            clearToken();
            const portal = getPortalType();
            if (portal === 'admin') {
                window.location.href = '/admin/login.html';
            } else {
                window.location.href = '/login.html';
            }
            return;
        }

        return data;
    } catch (error) {
        console.error('Request error:', error);
        showToast('网络请求失败', 'error');
        throw error;
    }
}

// GET 请求
function get(url, params = {}) {
    // 过滤掉 undefined 和 null 值
    const filteredParams = Object.fromEntries(
        Object.entries(params).filter(([_, v]) => v !== undefined && v !== null && v !== '')
    );
    const query = new URLSearchParams(filteredParams).toString();
    const fullUrl = query ? `${url}?${query}` : url;
    return request(fullUrl, { method: 'GET' });
}

// POST 请求
function post(url, data = {}) {
    return request(url, { method: 'POST', body: data });
}

// PUT 请求
function put(url, data = {}) {
    return request(url, { method: 'PUT', body: data });
}

// DELETE 请求
function del(url) {
    return request(url, { method: 'DELETE' });
}

// Toast 提示
function showToast(message, type = 'success', duration = 3000) {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// 确认对话框
function confirm(message, title = '确认') {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay active';
        overlay.innerHTML = `
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">${title}</h3>
                    <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
                </div>
                <div class="modal-body">
                    <p>${message}</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="confirmCancel">取消</button>
                    <button class="btn btn-primary" id="confirmOk">确定</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        overlay.querySelector('#confirmCancel').onclick = () => {
            overlay.remove();
            resolve(false);
        };

        overlay.querySelector('#confirmOk').onclick = () => {
            overlay.remove();
            resolve(true);
        };
    });
}

// 提示对话框（替代浏览器 alert）
function alert(message, title = '提示') {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay active';
        overlay.innerHTML = `
            <div class="modal" style="max-width:400px;">
                <div class="modal-header">
                    <h3 class="modal-title">${title}</h3>
                    <button class="modal-close" id="alertClose">&times;</button>
                </div>
                <div class="modal-body">
                    <p style="text-align:center;padding:var(--spacing-md) 0;">${message}</p>
                </div>
                <div class="modal-footer" style="justify-content:center;">
                    <button class="btn btn-primary" id="alertOk">确定</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        const closeAlert = () => {
            overlay.remove();
            resolve();
        };

        overlay.querySelector('#alertClose').onclick = closeAlert;
        overlay.querySelector('#alertOk').onclick = closeAlert;
    });
}

// 输入对话框（替代浏览器 prompt）
function prompt(message, defaultValue = '', title = '请输入') {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay active';
        overlay.innerHTML = `
            <div class="modal" style="max-width:450px;">
                <div class="modal-header">
                    <h3 class="modal-title">${title}</h3>
                    <button class="modal-close" id="promptClose">&times;</button>
                </div>
                <div class="modal-body">
                    <p style="margin-bottom:var(--spacing-sm);">${message}</p>
                    <input type="text" class="form-control" id="promptInput" value="${defaultValue}" placeholder="请输入...">
                </div>
                <div class="modal-footer">
                    <button class="btn" id="promptCancel">取消</button>
                    <button class="btn btn-primary" id="promptOk">确定</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        const input = overlay.querySelector('#promptInput');
        input.focus();

        const closePrompt = (value) => {
            overlay.remove();
            resolve(value);
        };

        overlay.querySelector('#promptClose').onclick = () => closePrompt(null);
        overlay.querySelector('#promptCancel').onclick = () => closePrompt(null);
        overlay.querySelector('#promptOk').onclick = () => closePrompt(input.value);

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                closePrompt(input.value);
            } else if (e.key === 'Escape') {
                closePrompt(null);
            }
        });
    });
}

// 格式化日期
function formatDate(dateStr, format = 'YYYY-MM-DD') {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return format
        .replace('YYYY', year)
        .replace('MM', month)
        .replace('DD', day)
        .replace('HH', hours)
        .replace('mm', minutes);
}

// 格式化金额
function formatMoney(amount) {
    if (amount === null || amount === undefined) return '0.00';
    return parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// 格式化数字
function formatNumber(num) {
    if (num === null || num === undefined) return '0';
    if (num >= 10000) {
        return (num / 10000).toFixed(1) + 'w';
    }
    return num.toString();
}

// 获取状态标签
function getStatusTag(status, type) {
    const statusMap = {
        task: {
            0: { text: '草稿', class: 'tag' },
            1: { text: '审核中', class: 'tag tag-warning' },
            2: { text: '进行中', class: 'tag tag-success' },
            3: { text: '已结束', class: 'tag tag-info' },
            4: { text: '已取消', class: 'tag' },
            5: { text: '审核拒绝', class: 'tag tag-danger' },
        },
        order: {
            1: { text: '已报名', class: 'tag tag-info' },
            2: { text: '已接单', class: 'tag tag-primary' },
            3: { text: '执行中', class: 'tag tag-warning' },
            4: { text: '待审核', class: 'tag tag-warning' },
            5: { text: '已完成', class: 'tag tag-success' },
            6: { text: '已拒绝', class: 'tag tag-danger' },
            7: { text: '已取消', class: 'tag' },
        },
        verify: {
            0: { text: '待审核', class: 'tag tag-warning' },
            1: { text: '已通过', class: 'tag tag-success' },
            2: { text: '已拒绝', class: 'tag tag-danger' },
        },
        withdrawal: {
            0: { text: '待审核', class: 'tag tag-warning' },
            1: { text: '已通过', class: 'tag tag-success' },
            2: { text: '已拒绝', class: 'tag tag-danger' },
            3: { text: '已打款', class: 'tag tag-success' },
            4: { text: '打款失败', class: 'tag tag-danger' },
        },
    };

    const item = statusMap[type]?.[status];
    if (!item) return `<span class="tag">${status}</span>`;
    return `<span class="${item.class}">${item.text}</span>`;
}

// 文件上传
async function uploadFile(file) {
    const formData = new FormData();
    formData.append('file', file);

    const token = getToken();
    const response = await fetch(API_BASE + '/upload', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
        },
        body: formData,
    });

    return response.json();
}

// 检查登录状态
function checkAuth() {
    const token = getToken();
    if (!token) {
        window.location.href = '/login.html';
        return false;
    }
    return true;
}

// 退出登录
function logout() {
    clearToken();
    const portal = getPortalType();
    if (portal === 'admin') {
        window.location.href = '/admin/login.html';
    } else {
        window.location.href = '/login.html';
    }
}

// 打开弹窗
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

// 关闭弹窗
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// 渲染分页
function renderPagination(container, pagination, onPageChange) {
    const { total, page, per_page, total_pages } = pagination;

    if (total_pages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = '';

    // 上一页
    html += `<span class="pagination-item ${page <= 1 ? 'disabled' : ''}" data-page="${page - 1}">&lt;</span>`;

    // 页码
    const start = Math.max(1, page - 2);
    const end = Math.min(total_pages, page + 2);

    if (start > 1) {
        html += `<span class="pagination-item" data-page="1">1</span>`;
        if (start > 2) html += `<span class="pagination-item disabled">...</span>`;
    }

    for (let i = start; i <= end; i++) {
        html += `<span class="pagination-item ${i === page ? 'active' : ''}" data-page="${i}">${i}</span>`;
    }

    if (end < total_pages) {
        if (end < total_pages - 1) html += `<span class="pagination-item disabled">...</span>`;
        html += `<span class="pagination-item" data-page="${total_pages}">${total_pages}</span>`;
    }

    // 下一页
    html += `<span class="pagination-item ${page >= total_pages ? 'disabled' : ''}" data-page="${page + 1}">&gt;</span>`;

    container.innerHTML = html;

    // 绑定事件
    container.querySelectorAll('.pagination-item:not(.disabled):not(.active)').forEach(item => {
        item.onclick = () => onPageChange(parseInt(item.dataset.page));
    });
}

// 防抖
function debounce(fn, delay = 300) {
    let timer = null;
    return function(...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}

// 节流
function throttle(fn, delay = 300) {
    let last = 0;
    return function(...args) {
        const now = Date.now();
        if (now - last >= delay) {
            last = now;
            fn.apply(this, args);
        }
    };
}

// ============================================
// 自定义 Select 下拉框组件 (Element Plus 风格)
// ============================================

/**
 * 初始化自定义 Select 组件
 * 将原生 select 转换为自定义样式的下拉框
 */
function initCustomSelects() {
    document.querySelectorAll('select.form-control').forEach(select => {
        // 跳过已经初始化的
        if (select.dataset.customized === 'true') return;
        select.dataset.customized = 'true';

        // 创建自定义 select 容器
        const wrapper = document.createElement('div');
        wrapper.className = 'custom-select';
        if (select.disabled) wrapper.classList.add('is-disabled');

        // 获取当前选中项
        const selectedOption = select.options[select.selectedIndex];
        const placeholder = select.options[0]?.value === '' ? select.options[0].text : '请选择';

        // 创建触发器
        const trigger = document.createElement('div');
        trigger.className = 'custom-select-trigger';
        trigger.innerHTML = `
            <span class="custom-select-value ${!selectedOption || selectedOption.value === '' ? 'custom-select-placeholder' : ''}">
                ${selectedOption ? selectedOption.text : placeholder}
            </span>
            <span class="custom-select-arrow"></span>
        `;

        // 创建下拉面板
        const dropdown = document.createElement('div');
        dropdown.className = 'custom-select-dropdown';

        // 创建选项列表
        const optionsList = document.createElement('div');
        optionsList.className = 'custom-select-options';

        Array.from(select.options).forEach((option, index) => {
            const optionEl = document.createElement('div');
            optionEl.className = 'custom-select-option';
            if (option.disabled) optionEl.classList.add('is-disabled');
            if (index === select.selectedIndex) optionEl.classList.add('is-selected');
            optionEl.dataset.value = option.value;
            optionEl.textContent = option.text;

            optionEl.addEventListener('click', (e) => {
                e.stopPropagation();
                if (option.disabled) return;

                // 更新原生 select 值
                select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));

                // 更新显示
                const valueEl = wrapper.querySelector('.custom-select-value');
                valueEl.textContent = option.text;
                valueEl.classList.toggle('custom-select-placeholder', option.value === '');

                // 更新选中状态
                optionsList.querySelectorAll('.custom-select-option').forEach(opt => {
                    opt.classList.remove('is-selected');
                });
                optionEl.classList.add('is-selected');

                // 关闭下拉框
                closeCustomSelect(wrapper);
            });

            optionsList.appendChild(optionEl);
        });

        dropdown.appendChild(optionsList);

        // 点击触发器切换下拉框
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            if (select.disabled) return;

            const isOpen = wrapper.classList.contains('is-focus');
            // 先关闭所有其他下拉框
            closeAllCustomSelects();

            if (!isOpen) {
                wrapper.classList.add('is-focus');
            }
        });

        // 组装结构
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(trigger);
        wrapper.appendChild(dropdown);
        wrapper.appendChild(select);

        // 保持原有宽度样式
        if (select.style.width) {
            wrapper.style.width = select.style.width;
            select.style.width = '';
        }
    });
}

/**
 * 关闭指定的自定义 select
 */
function closeCustomSelect(wrapper) {
    wrapper.classList.remove('is-focus');
}

/**
 * 关闭所有自定义 select
 */
function closeAllCustomSelects() {
    document.querySelectorAll('.custom-select.is-focus').forEach(wrapper => {
        wrapper.classList.remove('is-focus');
    });
}

/**
 * 更新自定义 select 的显示值（当原生 select 值被程序修改时调用）
 */
function updateCustomSelect(select) {
    const wrapper = select.closest('.custom-select');
    if (!wrapper) return;

    const selectedOption = select.options[select.selectedIndex];
    const valueEl = wrapper.querySelector('.custom-select-value');
    if (valueEl && selectedOption) {
        valueEl.textContent = selectedOption.text;
        valueEl.classList.toggle('custom-select-placeholder', selectedOption.value === '');
    }

    // 更新选项选中状态
    wrapper.querySelectorAll('.custom-select-option').forEach((opt, index) => {
        opt.classList.toggle('is-selected', index === select.selectedIndex);
    });
}

// 点击页面其他地方关闭下拉框
document.addEventListener('click', (e) => {
    if (!e.target.closest('.custom-select')) {
        closeAllCustomSelects();
    }
});

// 初始化
document.addEventListener('DOMContentLoaded', () => {
    // 检查是否需要登录
    const publicPages = ['/login.html', '/register.html', '/index.html', '/'];
    const currentPath = window.location.pathname;

    if (!publicPages.includes(currentPath) && !currentPath.startsWith('/showcase')) {
        checkAuth();
    }

    // 初始化自定义 Select 组件
    initCustomSelects();
});
