            </div>
        </main>
    </div>

    <!-- PWA Install Toast (Initially Hidden) -->
    <div id="pwa-toast" class="pwa-install-toast" style="display: none; position: fixed; bottom: 20px; left: 20px; right: 20px; background: linear-gradient(45deg, #1E459F, #CF2A2A); color: white; padding: 20px; border-radius: 15px; box-shadow: 0 8px 25px rgba(0,0,0,0.3); z-index: 9999;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="font-size: 2.5rem;">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <div>
                    <h5 style="margin: 0; color: #FABD32;">
                        <i class="fas fa-star"></i>
                        Install EMA Camp App
                    </h5>
                    <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 0.9rem;">
                        Get faster access with home screen shortcut!
                    </p>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button id="pwa-toast-install" class="btn" style="background: #FABD32; color: #1E459F; border: none; font-weight: bold; padding: 8px 15px;">
                    <i class="fas fa-download"></i>
                    Install
                </button>
                <button id="pwa-toast-dismiss" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: none; padding: 8px 12px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="<?= BASE_URL ?>assets/js/app.js"></script>
    
    <!-- PWA Registration Script -->
    <script>
        // Enhanced PWA Installation
        let deferredPrompt;
        
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            
            // Show install toast if not dismissed
            if (!localStorage.getItem('ema-pwa-dismissed')) {
                setTimeout(() => {
                    document.getElementById('pwa-toast').style.display = 'block';
                    document.getElementById('pwa-toast').style.animation = 'slideInUp 0.5s ease';
                }, 3000); // Show after 3 seconds
            }
        });

        // Install button click
        document.addEventListener('DOMContentLoaded', function() {
            const installBtn = document.getElementById('pwa-toast-install');
            const dismissBtn = document.getElementById('pwa-toast-dismiss');
            const toast = document.getElementById('pwa-toast');
            
            if (installBtn) {
                installBtn.addEventListener('click', async () => {
                    if (deferredPrompt) {
                        deferredPrompt.prompt();
                        const { outcome } = await deferredPrompt.userChoice;
                        
                        if (outcome === 'accepted') {
                            console.log('EMA Camp PWA installed');
                            toast.style.display = 'none';
                            
                            // Show success animation
                            showInstallSuccess();
                        } else {
                            console.log('EMA Camp PWA installation dismissed');
                        }
                        
                        deferredPrompt = null;
                    }
                });
            }
            
            if (dismissBtn) {
                dismissBtn.addEventListener('click', () => {
                    toast.style.animation = 'slideOutDown 0.5s ease';
                    setTimeout(() => {
                        toast.style.display = 'none';
                    }, 500);
                    localStorage.setItem('ema-pwa-dismissed', 'true');
                });
            }
        });

        // Success animation
        function showInstallSuccess() {
            const successDiv = document.createElement('div');
            successDiv.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: linear-gradient(45deg, #28a745, #20c997);
                color: white;
                padding: 40px;
                border-radius: 20px;
                text-align: center;
                z-index: 9999;
                box-shadow: 0 15px 40px rgba(0,0,0,0.3);
                animation: bounceIn 0.8s ease;
            `;
            
            successDiv.innerHTML = `
                <div style="font-size: 4rem; margin-bottom: 15px;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 style="margin: 0; margin-bottom: 10px;">Berhasil Diinstall!</h3>
                <p style="margin: 0; opacity: 0.9;">EMA Camp sekarang tersedia di home screen Anda</p>
            `;
            
            document.body.appendChild(successDiv);
            
            setTimeout(() => {
                successDiv.style.animation = 'fadeOut 0.5s ease';
                setTimeout(() => successDiv.remove(), 500);
            }, 3000);
        }

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Network status monitoring
        function updateNetworkIndicator() {
            const indicators = document.querySelectorAll('#online-status');
            indicators.forEach(indicator => {
                if (navigator.onLine) {
                    indicator.className = 'badge badge-success';
                    indicator.innerHTML = '<i class="fas fa-wifi"></i> Online';
                } else {
                    indicator.className = 'badge badge-warning';
                    indicator.innerHTML = '<i class="fas fa-wifi-slash"></i> Offline';
                }
            });
        }

        window.addEventListener('online', updateNetworkIndicator);
        window.addEventListener('offline', updateNetworkIndicator);
    </script>

    <!-- PWA Animations CSS -->
    <style>
        @keyframes slideInUp {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes slideOutDown {
            from { transform: translateY(0); opacity: 1; }
            to { transform: translateY(100%); opacity: 0; }
        }
        
        @keyframes bounceIn {
            0% { transform: translate(-50%, -50%) scale(0.3); opacity: 0; }
            50% { transform: translate(-50%, -50%) scale(1.05); }
            70% { transform: translate(-50%, -50%) scale(0.9); }
            100% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        /* Enhanced mobile app feel */
        @media (display-mode: standalone) {
            .header {
                padding-top: env(safe-area-inset-top, 15px);
            }
            
            body {
                -webkit-user-select: none;
                -webkit-tap-highlight-color: transparent;
            }
            
            .card {
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            }
        }
        
        /* Splash screen simulation */
        .splash-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #1E459F, #CF2A2A);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 1;
            transition: opacity 0.5s ease;
        }
        
        .splash-content {
            text-align: center;
            color: white;
        }
        
        .splash-logo {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            border-radius: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
    </style>

    // Add ini di footer.php (before </body>):
<script>
// Force reload on back button
window.onpageshow = function(event) {
    if (event.persisted) {
        window.location.reload();
    }
};

// Disable bfcache
window.onbeforeunload = function() {
    return undefined;
};
</script>
</body>
</html>