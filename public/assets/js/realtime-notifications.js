class NotificationManager {
    constructor() {
        this.eventSource = null;
        this.userId = document.body.dataset.userId;
        this.notificationBell = document.querySelector('#notifDropdown');
        this.notificationBadge = document.querySelector('#notifDropdown .badge');
        this.notificationList = document.querySelector('.dropdown-menu[aria-labelledby="notifDropdown"] > div:last-child');
        this.unreadCount = 0;
        
        // Audio notification
        this.notificationSound = null;
        this.soundEnabled = false;
        
        this.init();
    }

    init() {
        // Initialiser le son après la première interaction
        this.initSound();
        
        // Connect to Mercure hub
        this.connectToMercure();
        
        // Fetch initial notifications
        this.fetchNotifications();
        
        // Poll as fallback every 30 seconds
        setInterval(() => this.fetchNotifications(), 30000);
        
        // Mark as read on click
        this.setupMarkAsRead();
    }

    initSound() {
        // Attendre la première interaction utilisateur
        const enableSound = () => {
            if (!this.soundEnabled) {
                this.notificationSound = new Audio('/assets/sounds/admin-notification.mp3');
                this.notificationSound.volume = 0.5;
                
                // Précharger le son
                this.notificationSound.load();
                
                this.soundEnabled = true;
                console.log('✅ Notification sound enabled');
                
                // Retirer les listeners une fois activé
                document.removeEventListener('click', enableSound);
                document.removeEventListener('keydown', enableSound);
            }
        };
        
        // Activer le son au premier clic ou touche
        document.addEventListener('click', enableSound);
        document.addEventListener('keydown', enableSound);
    }

    connectToMercure() {
        if (!this.userId) return;

        const hubUrl = new URL('/notifications/user/' + this.userId, window.MERCURE_HUB_URL || 'http://localhost:3000/.well-known/mercure');
        
        this.eventSource = new EventSource(hubUrl);
        
        this.eventSource.onmessage = (event) => {
            console.log('📬 Notification reçue:', event.data);
            const notification = JSON.parse(event.data);
            this.addNotification(notification);
            this.showToast(notification);
            this.playNotificationSound();
        };

        this.eventSource.onerror = (error) => {
            console.error('Mercure connection error:', error);
            this.eventSource.close();
            
            // Reconnect after 5 seconds
            setTimeout(() => this.connectToMercure(), 5000);
        };
        
        console.log('🔌 Connected to Mercure:', hubUrl.toString());
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
                this.notificationBadge.style.display = 'inline-block';
            } else {
                this.notificationBadge.style.display = 'none';
            }
        }
        
        // Update list
        if (this.notificationList) {
            if (notifications.length === 0) {
                this.notificationList.innerHTML = '<div class="p-3 text-muted">No notifications ✅</div>';
            } else {
                this.notificationList.innerHTML = notifications.map(n => this.createNotificationHTML(n)).join('');
            }
        }
    }

    addNotification(notification) {
        this.unreadCount++;
        
        // Update badge
        if (this.notificationBadge) {
            this.notificationBadge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
            this.notificationBadge.style.display = 'inline-block';
        }
        
        // Prepend to list
        if (this.notificationList) {
            if (this.notificationList.querySelector('.text-muted')) {
                this.notificationList.innerHTML = '';
            }
            
            const notifElement = document.createElement('div');
            notifElement.innerHTML = this.createNotificationHTML(notification);
            this.notificationList.insertBefore(notifElement.firstElementChild, this.notificationList.firstChild);
            
            // Limit to 10 notifications
            const allNotifs = this.notificationList.querySelectorAll('.px-3.py-2');
            if (allNotifs.length > 10) {
                allNotifs[allNotifs.length - 1].remove();
            }
        }
    }

    createNotificationHTML(notification) {
        return `
            <div class="px-3 py-2" data-notification-id="${notification.id}"
                 style="border-bottom: 1px solid rgba(255,255,255,0.08); color:#fff; ${notification.isRead ? '' : 'background: rgba(255,255,255,0.06);'}">
                <div style="font-size: 14px;">
                    <span class="me-2">${notification.icon}</span>
                    <span class="${notification.isRead ? 'text-muted' : ''}">${notification.message}</span>
                </div>
                <div class="small text-muted mt-1">${notification.timeAgo}</div>

                <div class="mt-2 d-flex gap-2">
                    ${notification.link ? `<a href="${notification.link}" class="btn btn-sm btn-outline-light">View</a>` : ''}
                    ${!notification.isRead ? `<button type="button" class="btn btn-sm btn-outline-secondary mark-read-btn" data-id="${notification.id}">Mark read</button>` : ''}
                </div>
            </div>
        `;
    }

    setupMarkAsRead() {
        document.addEventListener('click', async (e) => {
            if (e.target.classList.contains('mark-read-btn')) {
                const notificationId = e.target.dataset.id;
                await this.markAsRead(notificationId);
            }
        });
    }

    async markAsRead(notificationId) {
        try {
            const response = await fetch(`/api/notifications/${notificationId}/mark-read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                const notifElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
                if (notifElement) {
                    notifElement.style.background = '';
                    const markBtn = notifElement.querySelector('.mark-read-btn');
                    if (markBtn) markBtn.remove();
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

    playNotificationSound() {
        if (!this.soundEnabled || !this.notificationSound) {
            console.log('🔇 Sound not enabled yet (waiting for user interaction)');
            return;
        }
        
        console.log('🔊 Playing notification sound...');
        
        // Reset le son pour pouvoir le rejouer
        this.notificationSound.currentTime = 0;
        
        // Jouer le son
        this.notificationSound.play()
            .then(() => {
                console.log('✅ Sound played successfully');
            })
            .catch(error => {
                console.error('❌ Could not play notification sound:', error);
            });
    }

    showToast(notification) {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = 'notification-toast';
        toast.innerHTML = `
            <div style="display: flex; align-items: start; gap: 10px;">
                <span style="font-size: 24px;">${notification.icon}</span>
                <div>
                    <strong>New Notification</strong>
                    <p style="margin: 5px 0 0 0; font-size: 14px;">${notification.message}</p>
                </div>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => toast.classList.add('show'), 100);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.body.dataset.userId) {
        new NotificationManager();
    }
});