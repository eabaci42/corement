/**
 * Corement Admin Panel JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        initCorementAdmin();
    });
    
    /**
     * Admin panel başlatma
     */
    function initCorementAdmin() {
        initBulkActions();
        initConfirmDialogs();
        initFormValidation();
        initTabSwitching();
        initAjaxActions();
        initTooltips();
    }
    
    /**
     * Toplu işlemler
     */
    function initBulkActions() {
        // Tümünü seç/seçme
        $('#cb-select-all').on('change', function() {
            $('input[name="comment_ids[]"]').prop('checked', this.checked);
        });
        
        // Toplu işlem formu gönderimi
        $('.bulkactions .button').on('click', function(e) {
            const action = $(this).siblings('select').val();
            const checkedItems = $('input[name="comment_ids[]"]:checked').length;
            
            if (action === '-1') {
                e.preventDefault();
                alert('Lütfen bir işlem seçin.');
                return false;
            }
            
            if (checkedItems === 0) {
                e.preventDefault();
                alert('Lütfen en az bir öğe seçin.');
                return false;
            }
            
            if (action === 'delete') {
                if (!confirm('Seçili yorumları kalıcı olarak silmek istediğinizden emin misiniz?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    }
    
    /**
     * Onay diyalogları
     */
    function initConfirmDialogs() {
        // Silme işlemleri için onay
        $('.corement-danger, a[href*="action=delete"]').on('click', function(e) {
            if (!confirm('Bu işlemi gerçekleştirmek istediğinizden emin misiniz?')) {
                e.preventDefault();
                return false;
            }
        });
        
        // Log temizleme onayı
        $('input[name="clear_logs"]').on('click', function(e) {
            if (!confirm('Tüm güvenlik loglarını silmek istediğinizden emin misiniz?')) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    /**
     * Form doğrulama
     */
    function initFormValidation() {
        // E-posta alanları
        $('input[type="email"]').on('blur', function() {
            const email = $(this).val();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                $(this).addClass('error');
                showFieldError($(this), 'Geçerli bir e-posta adresi girin.');
            } else {
                $(this).removeClass('error');
                hideFieldError($(this));
            }
        });
        
        // Sayı alanları
        $('input[type="number"]').on('input', function() {
            const value = parseInt($(this).val());
            const min = parseInt($(this).attr('min'));
            const max = parseInt($(this).attr('max'));
            
            if (value < min || value > max) {
                $(this).addClass('error');
                showFieldError($(this), `Değer ${min} ile ${max} arasında olmalıdır.`);
            } else {
                $(this).removeClass('error');
                hideFieldError($(this));
            }
        });
        
        // URL alanları
        $('input[type="url"]').on('blur', function() {
            const url = $(this).val();
            const urlRegex = /^https?:\/\/.+/;
            
            if (url && !urlRegex.test(url)) {
                $(this).addClass('error');
                showFieldError($(this), 'Geçerli bir URL girin (http:// veya https:// ile başlamalı).');
            } else {
                $(this).removeClass('error');
                hideFieldError($(this));
            }
        });
    }
    
    /**
     * Tab geçişleri
     */
    function initTabSwitching() {
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            const targetTab = $(this).attr('href').split('tab=')[1];
            
            // URL'yi güncelle
            const url = new URL(window.location);
            url.searchParams.set('tab', targetTab);
            window.history.pushState({}, '', url);
            
            // Sayfayı yenile
            window.location.reload();
        });
    }
    
    /**
     * AJAX işlemleri
     */
    function initAjaxActions() {
        // Hızlı moderasyon
        $('.corement-quick-action').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const action = $button.data('action');
            const commentId = $button.data('comment-id');
            
            if ($button.hasClass('loading')) {
                return;
            }
            
            $button.addClass('loading').prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'corement_quick_moderate',
                    comment_id: commentId,
                    moderate_action: action,
                    nonce: corementAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Satırı güncelle veya kaldır
                        const $row = $button.closest('tr');
                        
                        if (action === 'delete') {
                            $row.fadeOut(function() {
                                $(this).remove();
                            });
                        } else {
                            // Durum sütununu güncelle
                            const statusText = action === 'approve' ? 'Onaylandı' : 'Spam';
                            const statusClass = action === 'approve' ? 'corement-status-approved' : 'corement-status-spam';
                            
                            $row.find('.corement-status').removeClass().addClass('corement-status ' + statusClass).text(statusText);
                        }
                        
                        showMessage('İşlem başarıyla tamamlandı.', 'success');
                    } else {
                        showMessage(response.data.message || 'Bir hata oluştu.', 'error');
                    }
                },
                error: function() {
                    showMessage('Bağlantı hatası oluştu.', 'error');
                },
                complete: function() {
                    $button.removeClass('loading').prop('disabled', false);
                }
            });
        });
        
        // İstatistikleri yenile
        $('.corement-refresh-stats').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            $button.addClass('loading');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'corement_refresh_stats',
                    nonce: corementAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // İstatistikleri güncelle
                        $.each(response.data.stats, function(key, value) {
                            $('.corement-stat-' + key).text(value);
                        });
                        
                        showMessage('İstatistikler güncellendi.', 'success');
                    }
                },
                complete: function() {
                    $button.removeClass('loading');
                }
            });
        });
    }
    
    /**
     * Tooltip'ler
     */
    function initTooltips() {
        $('[title]').each(function() {
            const $element = $(this);
            const title = $element.attr('title');
            
            $element.removeAttr('title').on('mouseenter', function() {
                showTooltip($element, title);
            }).on('mouseleave', function() {
                hideTooltip();
            });
        });
    }
    
    /**
     * Alan hata mesajı göster
     */
    function showFieldError($field, message) {
        hideFieldError($field);
        
        const $error = $('<div class="corement-field-error" style="color: #e74c3c; font-size: 12px; margin-top: 5px;">' + message + '</div>');
        $field.after($error);
    }
    
    /**
     * Alan hata mesajını gizle
     */
    function hideFieldError($field) {
        $field.next('.corement-field-error').remove();
    }
    
    /**
     * Genel mesaj göster
     */
    function showMessage(message, type = 'info') {
        const $message = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after($message);
        
        // 5 saniye sonra kaldır
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Kapatma butonu
        $message.find('.notice-dismiss').on('click', function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        });
    }
    
    /**
     * Tooltip göster
     */
    function showTooltip($element, text) {
        const $tooltip = $('<div class="corement-tooltip" style="position: absolute; background: #333; color: #fff; padding: 5px 10px; border-radius: 4px; font-size: 12px; z-index: 9999;">' + text + '</div>');
        
        $('body').append($tooltip);
        
        const offset = $element.offset();
        $tooltip.css({
            top: offset.top - $tooltip.outerHeight() - 5,
            left: offset.left + ($element.outerWidth() / 2) - ($tooltip.outerWidth() / 2)
        });
    }
    
    /**
     * Tooltip gizle
     */
    function hideTooltip() {
        $('.corement-tooltip').remove();
    }
    
    /**
     * Sayfa yüklendiğinde çalışacak ek işlemler
     */
    $(window).on('load', function() {
        // İstatistik kartlarına animasyon ekle
        $('.corement-stat-card').each(function(index) {
            $(this).delay(index * 100).animate({
                opacity: 1,
                transform: 'translateY(0)'
            }, 300);
        });
    });
    
    // CSS animasyonları için başlangıç durumu
    $('.corement-stat-card').css({
        opacity: 0,
        transform: 'translateY(20px)'
    });
    
})(jQuery);

// Global admin fonksiyonları
window.corementAdmin = {
    showMessage: function(message, type) {
        // Global mesaj gösterme fonksiyonu
        console.log('[Corement] ' + message);
    },
    
    confirmAction: function(message) {
        return confirm(message || 'Bu işlemi gerçekleştirmek istediğinizden emin misiniz?');
    }
};

