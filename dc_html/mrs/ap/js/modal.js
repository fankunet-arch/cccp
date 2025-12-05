/**
 * 现代化模态框组件
 * 文件路径: dc_html/mrs/ap/js/modal.js
 * 说明: 替代传统的alert()和confirm()，支持中间模态框和抽屉式模态框
 */

class Modal {
    constructor() {
        this.overlay = null;
        this.container = null;
        this.resolveCallback = null;
        this.rejectCallback = null;
        this.currentType = 'center'; // 'center' or 'drawer'
    }

    /**
     * 创建模态框DOM结构
     * @param {string} type - 模态框类型：'center' 或 'drawer'
     */
    createModal(type = 'center') {
        // 如果已有模态框，先清理
        if (this.overlay) {
            this.cleanup();
        }

        this.currentType = type;

        this.overlay = document.createElement('div');
        this.overlay.className = 'modal-overlay';

        // 只有中间模态框才支持点击遮罩关闭
        if (type === 'center') {
            this.overlay.addEventListener('click', (e) => {
                if (e.target === this.overlay) {
                    this.close(false);
                }
            });
        }

        this.container = document.createElement('div');
        this.container.className = type === 'drawer' ? 'modal-drawer' : 'modal-container';

        this.overlay.appendChild(this.container);
        document.body.appendChild(this.overlay);
    }

    /**
     * 显示警告框（替代alert）
     * @param {string} message - 消息内容
     * @param {string} title - 标题（可选）
     * @param {string} type - 类型：info, success, warning, error
     * @returns {Promise}
     */
    alert(message, title = '提示', type = 'info') {
        return new Promise((resolve) => {
            this.createModal('center');

            const icons = {
                info: 'ℹ️',
                success: '✅',
                warning: '⚠️',
                error: '❌'
            };

            this.container.innerHTML = `
                <div class="modal-header">
                    <span class="modal-icon ${type}">${icons[type] || icons.info}</span>
                    <h3 class="modal-title">${this.escapeHtml(title)}</h3>
                    <button class="modal-close" data-action="close">&times;</button>
                </div>
                <div class="modal-body">
                    <p>${this.escapeHtml(message)}</p>
                </div>
                <div class="modal-footer">
                    <button class="modal-btn modal-btn-primary" data-action="confirm">确定</button>
                </div>
            `;

            this.resolveCallback = resolve;
            this.show();
        });
    }

    /**
     * 显示确认框（替代confirm）
     * @param {string} message - 消息内容
     * @param {string} title - 标题（可选）
     * @param {object} options - 配置选项
     * @returns {Promise<boolean>}
     */
    confirm(message, title = '确认', options = {}) {
        return new Promise((resolve) => {
            this.createModal('center');

            const {
                type = 'warning',
                confirmText = '确认',
                cancelText = '取消',
                confirmClass = 'modal-btn-danger'
            } = options;

            const icons = {
                info: 'ℹ️',
                success: '✅',
                warning: '⚠️',
                error: '❌'
            };

            this.container.innerHTML = `
                <div class="modal-header">
                    <span class="modal-icon ${type}">${icons[type] || icons.warning}</span>
                    <h3 class="modal-title">${this.escapeHtml(title)}</h3>
                    <button class="modal-close" data-action="close">&times;</button>
                </div>
                <div class="modal-body">
                    <p>${this.escapeHtml(message)}</p>
                </div>
                <div class="modal-footer">
                    <button class="modal-btn modal-btn-secondary" data-action="cancel">${this.escapeHtml(cancelText)}</button>
                    <button class="modal-btn ${confirmClass}" data-action="confirm">${this.escapeHtml(confirmText)}</button>
                </div>
            `;

            this.resolveCallback = resolve;
            this.show();
        });
    }

    /**
     * 显示自定义模态框
     * @param {object} config - 配置对象
     * @returns {Promise}
     */
    custom(config) {
        return new Promise((resolve, reject) => {
            const {
                title = '提示',
                content = '',
                showClose = true,
                footer = null,
                width = null,
                type = 'center' // 'center' 或 'drawer'
            } = config;

            this.createModal(type);

            if (width && type === 'center') {
                this.container.style.width = width;
            }

            const closeButton = showClose
                ? '<button class="modal-close" data-action="close">&times;</button>'
                : '';

            const footerHtml = footer || `
                <div class="modal-footer">
                    <button class="modal-btn modal-btn-secondary" data-action="cancel">取消</button>
                    <button class="modal-btn modal-btn-primary" data-action="confirm">确定</button>
                </div>
            `;

            this.container.innerHTML = `
                <div class="modal-header">
                    <h3 class="modal-title">${this.escapeHtml(title)}</h3>
                    ${closeButton}
                </div>
                <div class="modal-body">
                    ${content}
                </div>
                ${footerHtml}
            `;

            this.resolveCallback = resolve;
            this.rejectCallback = reject;
            this.show();
        });
    }

    /**
     * 显示抽屉式模态框（便捷方法）
     * @param {object} config - 配置对象
     * @returns {Promise}
     */
    drawer(config) {
        return this.custom({ ...config, type: 'drawer' });
    }

    /**
     * 显示模态框
     */
    show() {
        if (!this.overlay) return;

        // 添加事件监听
        this.container.addEventListener('click', this.handleClick.bind(this));

        // 先设置display，再添加active类触发动画
        this.overlay.style.display = 'block';

        // 使用requestAnimationFrame确保DOM更新后再添加active类
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                this.overlay.classList.add('active');
            });
        });

        // 禁用页面滚动
        document.body.style.overflow = 'hidden';

        // ESC键关闭
        this.escHandler = (e) => {
            if (e.key === 'Escape') {
                this.close(false);
            }
        };
        document.addEventListener('keydown', this.escHandler);
    }

    /**
     * 关闭模态框
     * @param {any} result - 返回结果
     */
    close(result) {
        if (!this.overlay) return;

        // 移除active类，触发退出动画
        this.overlay.classList.remove('active');

        // 等待动画完成后再清理DOM
        setTimeout(() => {
            this.cleanup();

            // 恢复页面滚动
            document.body.style.overflow = '';

            // 调用回调
            if (this.resolveCallback) {
                this.resolveCallback(result);
                this.resolveCallback = null;
            }
        }, 300); // 与CSS transition时间一致
    }

    /**
     * 清理DOM和事件监听
     */
    cleanup() {
        if (this.container) {
            this.container.removeEventListener('click', this.handleClick);
        }

        if (this.escHandler) {
            document.removeEventListener('keydown', this.escHandler);
            this.escHandler = null;
        }

        // 移除DOM
        if (this.overlay && this.overlay.parentNode) {
            this.overlay.parentNode.removeChild(this.overlay);
        }

        this.overlay = null;
        this.container = null;
    }

    /**
     * 处理点击事件
     */
    handleClick(e) {
        const action = e.target.dataset.action;

        if (action === 'confirm') {
            this.close(true);
        } else if (action === 'cancel' || action === 'close') {
            this.close(false);
        }
    }

    /**
     * 转义HTML特殊字符
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, (m) => map[m]);
    }
}

// 创建全局实例
window.modal = new Modal();

// 提供便捷方法
window.showAlert = (message, title, type) => window.modal.alert(message, title, type);
window.showConfirm = (message, title, options) => window.modal.confirm(message, title, options);
window.showModal = (config) => window.modal.custom(config);
window.showDrawer = (config) => window.modal.drawer(config);
