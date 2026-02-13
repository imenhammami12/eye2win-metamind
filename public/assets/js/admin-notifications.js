class AdminNotificationManager {
    constructor() {
        this.eventSource = null;
        this.userId = document.body.dataset.userId;
        this.notificationBell = document.getElementById('adminNotificationBell');
        this.notificationBadge = document.getElementById('adminNotificationBadge');
        this.notificationDropdown = document.getElementById('adminNotificationDropdown');
        this.notificationList = document.getElementById('adminNotificationList');
        this.unreadCount = 0;
        
        // Audio notification
        this.notificationSound = null;
        this.soundEnabled = false;
        
        if (!this.notificationBell) {
            console.warn('Admin notification bell not found');
            return;
        }
        
        this.init();
    }

    init() {
        this.initSound();
        this.setupDropdown();
        this.connectToMercure();
        this.fetchNotifications();
        this.setupMarkAllRead();
        
        // Poll as fallback every 30 seconds
        setInterval(() => this.fetchNotifications(), 30000);
    }

    initSound() {
        this.notificationSound = new Audio('/assets/sounds/admin-notification.mp3');
        this.notificationSound.volume = 0.5;
        this.notificationSound.load();
        
        const activateSound = () => {
            if (this.soundEnabled) return;
            
            const playPromise = this.notificationSound.play();
            if (playPromise !== undefined) {
                playPromise
                    .then(() => {
                        this.notificationSound.pause();
                        this.notificationSound.currentTime = 0;
                        this.soundEnabled = true;
                        console.log('✅ Admin notification sound enabled!');
                    })
                    .catch(() => {
                        console.log('⏳ Waiting for user interaction...');
                    });
            }
        };
        
        setTimeout(activateSound, 100);
        
        ['click', 'touchstart', 'keydown', 'mousemove'].forEach(eventType => {
            document.addEventListener(eventType, () => {
                if (!this.soundEnabled) activateSound();
            }, { once: true, passive: true });
        });
    }

    setupDropdown() {
        // Toggle dropdown
        this.notificationBell.addEventListener('click', (e) => {
            e.stopPropagation();
            this.notificationDropdown.classList.toggle('show');
        });
        
        // Close when clicking outside
        document.addEventListener('click', (e) => {
            if (!this.notificationDropdown.contains(e.target) && e.target !== this.notificationBell) {
                this.notificationDropdown.classList.remove('show');
            }
        });
    }

    connectToMercure() {
        if (!this.userId) {
            console.warn('⚠️ No user ID found');
            return;
        }

        const hubUrl = new URL(window.MERCURE_HUB_URL || 'http://localhost:3000/.well-known/mercure');
        hubUrl.searchParams.append('topic', 'notifications/user/' + this.userId);
        
        console.log('🔌 Admin connecting to Mercure:', hubUrl.toString());
        
        this.eventSource = new EventSource(hubUrl);
        
        this.eventSource.onopen = () => {
            console.log('✅ Admin connected to Mercure!');
        };
        
        this.eventSource.onmessage = (event) => {
            console.log('📬 Admin notification received:', event.data);
            try {
                const notification = JSON.parse(event.data);
                this.addNotification(notification);
                this.showToast(notification);
                this.playNotificationSound();
            } catch (error) {
                console.error('❌ Error parsing notification:', error);
            }
        };

        this.eventSource.onerror = (error) => {
            console.error('❌ Mercure error:', error);
            this.eventSource.close();
            setTimeout(() => this.connectToMercure(), 5000);
        };
    }

    async fetchNotifications() {
        try {
            const response = await fetch('/api/notifications/unread');
            const data = await response.json();
            
            if (data.success) {
                this.updateNotificationUI(data.notifications, data.count);
            }
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
        }
    }

    updateNotificationUI(notifications, count) {
        this.unreadCount = count;
        
        // Update badge
        if (this.notificationBadge) {
            if (count > 0) {
                this.notificationBadge.textContent = count > 99 ? '99+' : count;
                this.notificationBadge.style.display = 'flex';
            } else {
                this.notificationBadge.style.display = 'none';
            }
        }
        
        // Update list
        if (this.notificationList) {
            if (notifications.length === 0) {
                this.notificationList.innerHTML = `
                    <div class="empty-notifications">
                        <i class="bi bi-bell-slash"></i>
                        <p>No new notifications</p>
                    </div>
                `;
            } else {
                this.notificationList.innerHTML = notifications.map(n => this.createNotificationHTML(n)).join('');
                this.attachNotificationListeners();
            }
        }
    }

    addNotification(notification) {
        this.unreadCount++;
        
        // Update badge
        if (this.notificationBadge) {
            this.notificationBadge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
            this.notificationBadge.style.display = 'flex';
        }
        
        // Prepend to list
        if (this.notificationList) {
            const emptyState = this.notificationList.querySelector('.empty-notifications');
            if (emptyState) {
                this.notificationList.innerHTML = '';
            }
            
            const notifElement = document.createElement('div');
            notifElement.innerHTML = this.createNotificationHTML(notification);
            this.notificationList.insertBefore(notifElement.firstChild, this.notificationList.firstChild);
            
            this.attachNotificationListeners();
            
            // Limit to 10 notifications
            const allNotifs = this.notificationList.querySelectorAll('.notification-item');
            if (allNotifs.length > 10) {
                allNotifs[allNotifs.length - 1].remove();
            }
        }
    }

    createNotificationHTML(notification) {
        return `
            <div class="notification-item ${notification.isRead ? '' : 'unread'}" data-notification-id="${notification.id}">
                <div class="notification-content">
                    <div class="notification-icon">${notification.icon}</div>
                    <div class="notification-text">
                        <p>${notification.message}</p>
                        <span class="notification-time">${notification.timeAgo}</span>
                        ${notification.link && !notification.isRead ? `
                            <div class="notification-actions">
                                <a href="${notification.link}" class="btn btn-sm btn-outline-light">View</a>
                                <button class="btn btn-sm btn-outline-secondary mark-read-btn" data-id="${notification.id}">Mark read</button>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    attachNotificationListeners() {
        // Mark as read buttons
        document.querySelectorAll('.mark-read-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                await this.markAsRead(btn.dataset.id);
            });
        });
    }

    async markAsRead(notificationId) {
        try {
            const response = await fetch(`/api/notifications/${notificationId}/mark-read`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            
            const data = await response.json();
            
            if (data.success) {
                const notifElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
                if (notifElement) {
                    notifElement.classList.remove('unread');
                    const actions = notifElement.querySelector('.notification-actions');
                    if (actions) actions.remove();
                }
                
                this.unreadCount = Math.max(0, this.unreadCount - 1);
                if (this.notificationBadge) {
                    if (this.unreadCount > 0) {
                        this.notificationBadge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
                    } else {
                        this.notificationBadge.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    }

    setupMarkAllRead() {
        const markAllBtn = document.getElementById('markAllRead');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', async () => {
                try {
                    const response = await fetch('/api/notifications/mark-all-read', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.fetchNotifications();
                    }
                } catch (error) {
                    console.error('Failed to mark all as read:', error);
                }
            });
        }
    }

    playNotificationSound() {
        if (!this.notificationSound) return;
        
        console.log('🔊 Playing admin notification sound...');
        this.notificationSound.currentTime = 0;
        this.notificationSound.play()
            .then(() => console.log('✅ Admin sound played!'))
            .catch(error => console.error('❌ Sound error:', error));
    }

    showToast(notification) {
        const toast = document.createElement('div');
        toast.className = 'admin-notification-toast';
        toast.innerHTML = `
            <div class="toast-content">
                <div class="toast-icon">${notification.icon}</div>
                <div class="toast-text">
                    <div class="toast-title">New Notification</div>
                    <p class="toast-message">${notification.message}</p>
                </div>
                <button class="toast-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
            </div>
        `;
        
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 100);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 400);
        }, 5000);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const userId = document.body.dataset.userId;
    if (userId) {
        new AdminNotificationManager();
    }
});