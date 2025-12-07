/**
 * 返回顶部按钮组件
 * 使用方法：在页面中引入此JS文件，然后调用 BackToTop.init()
 */

/* 返回顶部按钮样式 */
const backToTopStyle = `
.back-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    box-shadow: 0 2px 10px rgba(0, 123, 255, 0.3);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    text-decoration: none;
}

.back-to-top:hover {
    background: linear-gradient(135deg, #0056b3, #004085);
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0, 123, 255, 0.4);
}

.back-to-top.visible {
    opacity: 1;
    visibility: visible;
}

.back-to-top:active {
    transform: translateY(0);
}

@media (max-width: 768px) {
    .back-to-top {
        bottom: 20px;
        right: 20px;
        width: 45px;
        height: 45px;
        font-size: 16px;
    }
}
`;

// 返回顶部按钮类
class BackToTopButton {
    constructor(options = {}) {
        this.options = {
            threshold: options.threshold || 300,
            duration: options.duration || 500,
            icon: options.icon || '↑',
            ...options
        };
        this.button = null;
        this.isVisible = false;
    }

    init() {
        this.injectStyles();
        this.createButton();
        this.bindEvents();
    }

    injectStyles() {
        if (document.getElementById('back-to-top-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'back-to-top-styles';
        style.textContent = backToTopStyle;
        document.head.appendChild(style);
    }

    createButton() {
        this.button = document.createElement('button');
        this.button.className = 'back-to-top';
        this.button.innerHTML = this.options.icon;
        this.button.setAttribute('aria-label', '返回顶部');
        this.button.setAttribute('title', '返回顶部');
        document.body.appendChild(this.button);
    }

    bindEvents() {
        // 点击事件
        this.button.addEventListener('click', () => {
            this.scrollToTop();
        });

        // 滚动事件
        let ticking = false;
        const onScroll = () => {
            if (!ticking) {
                requestAnimationFrame(() => {
                    this.handleScroll();
                    ticking = false;
                });
                ticking = true;
            }
        };
        
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    handleScroll() {
        // 确保按钮存在
        if (!this.button) {
            return;
        }
        
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const shouldShow = scrollTop > this.options.threshold;
        
        if (shouldShow !== this.isVisible) {
            this.isVisible = shouldShow;
            this.button.classList.toggle('visible', shouldShow);
        }
    }

    scrollToTop() {
        const startPosition = window.pageYOffset;
        const startTime = performance.now();
        const duration = this.options.duration;

        const animateScroll = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const easeInOutQuad = progress < 0.5 
                ? 2 * progress * progress 
                : 1 - Math.pow(-2 * progress + 2, 2) / 2;
            
            const currentPosition = startPosition * (1 - easeInOutQuad);
            
            window.scrollTo(0, currentPosition);
            
            if (progress < 1) {
                requestAnimationFrame(animateScroll);
            }
        };

        requestAnimationFrame(animateScroll);
    }

    destroy() {
        if (this.button) {
            this.button.remove();
            this.button = null;
        }
        const style = document.getElementById('back-to-top-styles');
        if (style) {
            style.remove();
        }
    }
}

// 全局实例
let backToTopInstance = null;

// 挂载到全局对象
window.BackToTop = {
    init: function(options = {}) {
        // 销毁旧实例
        if (backToTopInstance) {
            backToTopInstance.destroy();
        }
        
        // 创建新实例
        backToTopInstance = new BackToTopButton(options);
        backToTopInstance.init();
        
        return backToTopInstance;
    },
    
    destroy: function() {
        if (backToTopInstance) {
            backToTopInstance.destroy();
            backToTopInstance = null;
        }
    },
    
    getInstance: function() {
        return backToTopInstance;
    }
};