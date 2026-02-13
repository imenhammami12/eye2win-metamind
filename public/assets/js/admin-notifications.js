// Admin Notifications System - Using Polling + Mercure
// Based on working frontend implementation

class AdminNotificationManager {
    constructor() {
        console.log('🚀 AdminNotificationManager initializing...');
        
        this.userId = document.body.dataset.userId;
        this.notificationBell = document.getElementById('adminNotificationBell');
        this.notificationBadge = document.getElementById('adminNotificationBadge');
        this.notificationDropdown = document.getElementById('adminNotificationDropdown');
        this.notificationList = document.getElementById('adminNotificationList');
        this.unreadCount = 0;
        this.lastCheckTime = Date.now();
        
        console.log('📊 Config:', {
            userId: this.userId,
            hasBell: !!this.notificationBell,
            hasBadge: !!this.notificationBadge,
            hasDropdown: !!this.notificationDropdown,
            hasList: !!this.notificationList
        });
        
        // Audio notification
        this.notificationSound = null;
        this.soundEnabled = false;
        
        if (!this.notificationBell) {
            console.error('❌ Admin notification bell not found');
            return;
        }
        
        if (!this.userId) {
            console.error('❌ No user ID found');
            return;
        }
        
        console.log('✅ All elements found, initializing...');
        this.init();
    }

    init() {
        console.log('🔧 Initializing components...');
        this.initSound();
        this.setupDropdown();
        this.setupMarkAllRead();
        
        // Initial check
        this.checkNotifications();
        
        // Poll every 10 seconds (more frequent for admins)
        setInterval(() => {
            console.log('🔄 Polling notifications...');
            this.checkNotifications();
        }, 10000);
        
        // Try Mercure in parallel for instant updates
        this.connectToMercure();
        
        console.log('✅ Initialization complete');
    }

    initSound() {
        console.log('🔊 Initializing sound...');
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
                        console.log('✅ Sound enabled!');
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
        console.log('📋 Setting up dropdown...');
        
        this.notificationBell.addEventListener('click', (e) => {
            e.stopPropagation();
            console.log('🔔 Bell clicked');
            this.notificationDropdown.classList.toggle('show');
        });
        
        document.addEventListener('click', (e) => {
            if (!this.notificationDropdown.contains(e.target) && e.target !== this.notificationBell) {
                this.notificationDropdown.classList.remove('show');
            }
        });
    }

    // MAIN METHOD: Check for notifications via API
    async checkNotifications() {
        try {
            const response = await fetch('/api/notifications/unread?t=' + Date.now(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                console.error('❌ API response not ok:', response.status);
                return;
            }
            
            const data = await response.json();
            console.log('📦 API data:', data);
            
            if (data.success) {
                const oldCount = this.unreadCount;
                this.updateNotificationUI(data.notifications, data.count);
                
                // If count increased, play sound
                if (data.count > oldCount && oldCount !== 0) {
                    console.log('🔔 New notifications detected!');
                    this.playNotificationSound();
                    
                    // Show toast for newest notification
                    if (data.notifications.length > 0) {
                        this.showToast(data.notifications[0]);
                    }
                }
            }
        } catch (error) {
            console.error('❌ Failed to check notifications:', error);
        }
    }

    updateNotificationUI(notifications, count) {
        console.log('🎨 Updating UI:', count, 'notifications');
        
        this.unreadCount = count;
        
        // Update badge
        if (this.notificationBadge) {
            if (count > 0) {
                this.notificationBadge.textContent = count > 99 ? '99+' : count;
                this.notificationBadge.style.display = 'flex';
                console.log('🔵 Badge:', this.notificationBadge.textContent);
            } else {
                this.notificationBadge.style.display = 'none';
                console.log('👻 Badge hidden');
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
                console.log('✅ List updated');
            }
        }
    }

    createNotificationHTML(notification) {
        return `
            <div class="notification-item ${notification.isRead ? '' : 'unread'}" data-notification-id="${notification.id}">
                <div class="notification-content">
                    <div class="notification-icon">${notification.icon || '🔔'}</div>
                    <div class="notification-text">
                        <p>${this.escapeHtml(notification.message)}</p>
                        <span class="notification-time">${notification.timeAgo}</span>
                        ${notification.link && !notification.isRead ? `
                            <div class="notification-actions">
                                <a href="${this.escapeHtml(notification.link)}" class="btn btn-sm btn-outline-light">View</a>
                                <button class="btn btn-sm btn-outline-secondary mark-read-btn" data-id="${notification.id}">Mark read</button>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    attachNotificationListeners() {
        document.querySelectorAll('.mark-read-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                await this.markAsRead(btn.dataset.id);
            });
        });
    }

    async markAsRead(notificationId) {
        console.log('✓ Marking as read:', notificationId);
        try {
            const response = await fetch(`/api/notifications/${notificationId}/mark-read`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.log('✅ Marked as read');
                this.checkNotifications(); // Refresh
            }
        } catch (error) {
            console.error('❌ Failed to mark as read:', error);
        }
    }

    setupMarkAllRead() {
        const markAllBtn = document.getElementById('markAllRead');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', async () => {
                console.log('✓ Marking all as read...');
                try {
                    const response = await fetch('/api/notifications/mark-all-read', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        console.log('✅ All marked as read');
                        this.checkNotifications();
                    }
                } catch (error) {
                    console.error('❌ Failed to mark all as read:', error);
                }
            });
        }
    }

    playNotificationSound() {
        if (!this.notificationSound || !this.soundEnabled) {
            console.log('🔇 Sound not available');
            return;
        }
        
        console.log('🔊 Playing sound...');
        this.notificationSound.currentTime = 0;
        this.notificationSound.play()
            .then(() => console.log('✅ Sound played!'))
            .catch(error => console.error('❌ Sound error:', error));
    }

    showToast(notification) {
        console.log('🍞 Showing toast');
        const toast = document.createElement('div');
        toast.className = 'admin-notification-toast';
        toast.innerHTML = `
            <div class="toast-content">
                <div class="toast-icon">${notification.icon || '🔔'}</div>
                <div class="toast-text">
                    <div class="toast-title">New Notification</div>
                    <p class="toast-message">${this.escapeHtml(notification.message)}</p>
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

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Mercure connection (bonus for instant updates)
    connectToMercure() {
        if (!window.MERCURE_HUB_URL) {
            console.log('⚠️ No Mercure hub configured');
            return;
        }

        try {
            const hubUrl = new URL(window.MERCURE_HUB_URL);
            hubUrl.searchParams.append('topic', 'notifications/user/' + this.userId);
            
            console.log('🔌 Connecting to Mercure:', hubUrl.toString());
            
            this.eventSource = new EventSource(hubUrl);
            
            this.eventSource.onopen = () => {
                console.log('✅ Mercure connected!');
            };
            
            this.eventSource.onmessage = (event) => {
                console.log('📬 Mercure notification received!');
                // When Mercure sends a notification, refresh immediately
                this.checkNotifications();
            };

            this.eventSource.onerror = (error) => {
                console.error('❌ Mercure error:', error);
                this.eventSource.close();
                // Don't retry - polling will handle it
            };
        } catch (error) {
            console.error('❌ Failed to connect to Mercure:', error);
        }
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    console.log('🌐 DOM loaded');
    const userId = document.body.dataset.userId;
    console.log('👤 User ID:', userId);
    
    if (userId) {
        console.log('✅ Initializing AdminNotificationManager...');
        window.adminNotificationManager = new AdminNotificationManager();
    } else {
        console.warn('⚠️ No user ID found');
    }
});

console.log('📜 admin-notifications.js loaded');