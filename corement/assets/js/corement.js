/**
 * Corement - Modern Comment System JavaScript
 */

(function($) {
    'use strict';
    
    // Global değişkenler
    let isSubmitting = false;
    
    $(document).ready(function() {
        initCorement();
    });
    
    /**
     * Ana başlatma fonksiyonu
     */
    function initCorement() {
        initThemeDetection();
        initFormHandlers();
        initMediaPreview();
        initReactionHandlers();
        initVoteHandlers();
        initReplyHandlers();
        initFormValidation();
    }
    
    /**
     * Tema algılama ve otomatik mod
     */
    function initThemeDetection() {
        const container = $('.corement-container');
        if (!container.length) return;
        
        // Sistem teması algılama
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        // Madara teması algılama
        const isMadara = $('body').hasClass('madara') || 
                        $('.madara').length > 0 || 
                        $('link[href*="madara"]').length > 0 ||
                        $('script[src*="madara"]').length > 0;
        
        // wp-manga teması algılama
        const isWpManga = $('body').hasClass('wp-manga') || 
                         $('.wp-manga').length > 0 ||
                         $('link[href*="wp-manga"]').length > 0 ||
                         $('script[src*="wp-manga"]').length > 0;
        
        // Tema sınıflarını ekle
        if (isMadara) {
            container.addClass('corement-madara');
            $('body').addClass('corement-madara-detected');
        }
        if (isWpManga) {
            container.addClass('corement-wp-manga');
            $('body').addClass('corement-wp-manga-detected');
        }
        
        // Karanlık/aydınlık mod algılama
        const isDarkMode = prefersDark || 
                          $('body').hasClass('dark') || 
                          $('body').hasClass('dark-mode') ||
                          $('html').hasClass('dark') ||
                          $('html').hasClass('dark-mode') ||
                          $('.dark-mode').length > 0;
        
        // Tema sınıfını uygula
        container.addClass(isDarkMode ? 'corement-dark' : 'corement-light');
        
        // Tema değişikliğini dinle
        const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
        darkModeQuery.addEventListener('change', function(e) {
            container.removeClass('corement-dark corement-light');
            container.addClass(e.matches ? 'corement-dark' : 'corement-light');
        });
        
        // MutationObserver ile dinamik tema değişikliklerini izle
        if (window.MutationObserver) {
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && 
                        (mutation.attributeName === 'class' || mutation.attributeName === 'data-theme')) {
                        
                        const isDark = $('body').hasClass('dark') || 
                                      $('body').hasClass('dark-mode') ||
                                      $('html').hasClass('dark') ||
                                      $('html').hasClass('dark-mode') ||
                                      $('body').attr('data-theme') === 'dark';
                        
                        container.removeClass('corement-dark corement-light');
                        container.addClass(isDark ? 'corement-dark' : 'corement-light');
                    }
                });
            });
            
            observer.observe(document.body, {
                attributes: true,
                attributeFilter: ['class', 'data-theme']
            });
            
            observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class', 'data-theme']
            });
        }
        
        // CSS custom properties için tema değişkenlerini ayarla
        updateThemeVariables(container, isMadara, isWpManga, isDarkMode);
    }
    
    /**
     * Tema değişkenlerini güncelle
     */
    function updateThemeVariables(container, isMadara, isWpManga, isDarkMode) {
        const root = container[0];
        if (!root) return;
        
        // Madara teması için özel renkler
        if (isMadara) {
            root.style.setProperty('--corement-accent-primary', '#ff6b35');
            root.style.setProperty('--corement-accent-secondary', '#f7931e');
            root.style.setProperty('--corement-border-radius', '0.25rem');
        }
        
        // WP-Manga teması için özel renkler
        if (isWpManga) {
            root.style.setProperty('--corement-accent-primary', '#e91e63');
            root.style.setProperty('--corement-accent-secondary', '#9c27b0');
            root.style.setProperty('--corement-border-radius', '0.5rem');
        }
        
        // Karanlık mod için ek ayarlamalar
        if (isDarkMode) {
            root.style.setProperty('--corement-shadow', '0 1px 3px 0 rgba(255, 255, 255, 0.1), 0 1px 2px 0 rgba(255, 255, 255, 0.06)');
            root.style.setProperty('--corement-shadow-md', '0 4px 6px -1px rgba(255, 255, 255, 0.1), 0 2px 4px -1px rgba(255, 255, 255, 0.06)');
        }
    }
    
    /**
     * Form işleyicileri
     */
    function initFormHandlers() {
        // Ana form gönderimi
        $(document).on('submit', '#corement-comment-form', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            
            const form = $(this);
            const submitBtn = form.find('#corement-submit-btn');
            const btnText = submitBtn.find('.corement-btn-text');
            const btnLoading = submitBtn.find('.corement-btn-loading');
            
            // Loading durumu
            isSubmitting = true;
            submitBtn.prop('disabled', true);
            btnText.hide();
            btnLoading.show();
            
            // Form doğrulama
            if (!validateForm(form)) {
                resetSubmitButton(submitBtn, btnText, btnLoading);
                e.preventDefault();
                return false;
            }
        });
        
        // Yanıt formu gönderimi
        $(document).on('submit', '.corement-reply-form-inner', function(e) {
            const form = $(this);
            const submitBtn = form.find('.corement-reply-submit');
            
            submitBtn.prop('disabled', true).text('Gönderiliyor...');
            
            // Form doğrulama
            if (!validateForm(form)) {
                submitBtn.prop('disabled', false).text('Yanıtı Gönder');
                e.preventDefault();
                return false;
            }
        });
    }
    
    /**
     * Medya önizleme
     */
    function initMediaPreview() {
        $(document).on('change', 'input[type="file"][name="corement_media"]', function() {
            const input = this;
            const file = input.files[0];
            const preview = $(input).closest('.corement-media-field, .corement-reply-media').find('.corement-media-preview');
            
            // Önizlemeyi temizle
            preview.empty();
            
            if (!file) return;
            
            // Dosya türü kontrolü
            if (!file.type.startsWith('image/')) {
                showMessage('Sadece resim dosyaları yükleyebilirsiniz.', 'error');
                input.value = '';
                return;
            }
            
            // Dosya boyutu kontrolü (MB)
            const maxSize = parseInt(corement_ajax.max_file_size || 2) * 1024 * 1024;
            if (file.size > maxSize) {
                showMessage(`Dosya boyutu ${maxSize / 1024 / 1024}MB'dan büyük olamaz.`, 'error');
                input.value = '';
                return;
            }
            
            // Önizleme oluştur
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = $('<img>')
                    .attr('src', e.target.result)
                    .addClass('corement-media-preview-img')
                    .css({
                        'max-width': '150px',
                        'max-height': '150px',
                        'border-radius': '6px',
                        'margin-top': '10px'
                    });
                
                const removeBtn = $('<button>')
                    .attr('type', 'button')
                    .addClass('corement-media-remove')
                    .text('×')
                    .css({
                        'position': 'absolute',
                        'top': '5px',
                        'right': '5px',
                        'background': 'rgba(0,0,0,0.7)',
                        'color': 'white',
                        'border': 'none',
                        'border-radius': '50%',
                        'width': '20px',
                        'height': '20px',
                        'cursor': 'pointer'
                    });
                
                const wrapper = $('<div>')
                    .css('position', 'relative')
                    .append(img)
                    .append(removeBtn);
                
                preview.append(wrapper);
                
                // Kaldırma butonu
                removeBtn.on('click', function() {
                    input.value = '';
                    preview.empty();
                });
            };
            
            reader.readAsDataURL(file);
        });
    }
    
    /**
     * Emoji tepki işleyicileri
     */
    function initReactionHandlers() {
        $(document).on('click', '.corement-reaction', function(e) {
            e.preventDefault();
            
            const btn = $(this);
            const commentId = btn.data('comment-id');
            const reaction = btn.data('reaction');
            
            if (btn.hasClass('loading')) return;
            
            btn.addClass('loading');
            
            $.ajax({
                url: corement_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'corement_react',
                    comment_id: commentId,
                    reaction: reaction,
                    nonce: corement_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        updateReactionCounts(commentId, response.data.counts);
                        
                        // Aktif durumu güncelle
                        const reactionBtns = $(`.corement-reaction[data-comment-id="${commentId}"]`);
                        reactionBtns.removeClass('active');
                        
                        if (response.data.action === 'added') {
                            btn.addClass('active');
                        }
                    } else {
                        showMessage(response.data.message || 'Bir hata oluştu.', 'error');
                    }
                },
                error: function() {
                    showMessage('Bağlantı hatası. Lütfen tekrar deneyin.', 'error');
                },
                complete: function() {
                    btn.removeClass('loading');
                }
            });
        });
    }
    
    /**
     * Oy verme işleyicileri
     */
    function initVoteHandlers() {
        $(document).on('click', '.corement-vote', function(e) {
            e.preventDefault();
            
            const btn = $(this);
            const commentId = btn.data('comment-id');
            const vote = btn.data('vote');
            
            if (btn.hasClass('loading')) return;
            
            btn.addClass('loading');
            
            $.ajax({
                url: corement_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'corement_vote',
                    comment_id: commentId,
                    vote: vote,
                    nonce: corement_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        updateVoteCounts(commentId, response.data.counts);
                        
                        // Aktif durumu güncelle
                        const voteBtns = $(`.corement-vote[data-comment-id="${commentId}"]`);
                        voteBtns.removeClass('active');
                        
                        if (response.data.action === 'added') {
                            btn.addClass('active');
                        }
                    } else {
                        showMessage(response.data.message || 'Bir hata oluştu.', 'error');
                    }
                },
                error: function() {
                    showMessage('Bağlantı hatası. Lütfen tekrar deneyin.', 'error');
                },
                complete: function() {
                    btn.removeClass('loading');
                }
            });
        });
    }
    
    /**
     * Yanıt işleyicileri
     */
    function initReplyHandlers() {
        // Yanıtla butonuna tıklama
        $(document).on('click', '.corement-reply-btn', function(e) {
            e.preventDefault();
            
            const btn = $(this);
            const commentId = btn.data('comment-id');
            const replyForm = $(`#corement-reply-form-${commentId}`);
            
            // Diğer açık formları kapat
            $('.corement-reply-form').not(replyForm).slideUp();
            
            // Bu formu aç/kapat
            replyForm.slideToggle();
            
            // Textarea'ya odaklan
            if (replyForm.is(':visible')) {
                setTimeout(() => {
                    replyForm.find('textarea').focus();
                }, 300);
            }
        });
        
        // Yanıt iptal butonu
        $(document).on('click', '.corement-reply-cancel', function(e) {
            e.preventDefault();
            
            const form = $(this).closest('.corement-reply-form');
            form.slideUp();
            
            // Formu temizle
            form.find('input[type="text"], input[type="email"], textarea').val('');
            form.find('input[type="file"]').val('');
            form.find('.corement-media-preview').empty();
        });
    }
    
    /**
     * Form doğrulama
     */
    function initFormValidation() {
        // Gerçek zamanlı doğrulama
        $(document).on('input', '.corement-input, .corement-textarea', function() {
            const field = $(this);
            validateField(field);
        });
    }
    
    /**
     * Form doğrulama fonksiyonu
     */
    function validateForm(form) {
        let isValid = true;
        
        // Gerekli alanları kontrol et
        form.find('input[required], textarea[required]').each(function() {
            const field = $(this);
            if (!validateField(field)) {
                isValid = false;
            }
        });
        
        // E-posta formatı kontrolü
        form.find('input[type="email"]').each(function() {
            const field = $(this);
            const email = field.val().trim();
            
            if (email && !isValidEmail(email)) {
                showFieldError(field, 'Geçerli bir e-posta adresi girin.');
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    /**
     * Tek alan doğrulama
     */
    function validateField(field) {
        const value = field.val().trim();
        const isRequired = field.prop('required');
        
        // Boş alan kontrolü
        if (isRequired && !value) {
            showFieldError(field, 'Bu alan zorunludur.');
            return false;
        }
        
        // Minimum uzunluk kontrolü
        if (field.is('textarea') && value && value.length < 3) {
            showFieldError(field, 'En az 3 karakter girmelisiniz.');
            return false;
        }
        
        // Maksimum uzunluk kontrolü
        if (field.is('textarea') && value && value.length > 5000) {
            showFieldError(field, 'En fazla 5000 karakter girebilirsiniz.');
            return false;
        }
        
        // Hata mesajını kaldır
        hideFieldError(field);
        return true;
    }
    
    /**
     * Alan hata mesajı göster
     */
    function showFieldError(field, message) {
        hideFieldError(field);
        
        const error = $('<div>')
            .addClass('corement-field-error')
            .text(message)
            .css({
                'color': '#e74c3c',
                'font-size': '12px',
                'margin-top': '5px'
            });
        
        field.addClass('corement-field-invalid')
            .css('border-color', '#e74c3c')
            .after(error);
    }
    
    /**
     * Alan hata mesajını gizle
     */
    function hideFieldError(field) {
        field.removeClass('corement-field-invalid')
            .css('border-color', '')
            .next('.corement-field-error').remove();
    }
    
    /**
     * E-posta doğrulama
     */
    function isValidEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }
    
    /**
     * Tepki sayılarını güncelle
     */
    function updateReactionCounts(commentId, counts) {
        const comment = $(`.corement-comment[data-comment-id="${commentId}"]`);
        
        Object.keys(counts).forEach(function(reaction) {
            const btn = comment.find(`.corement-reaction[data-reaction="${reaction}"]`);
            const countSpan = btn.find('.corement-reaction-count');
            const count = counts[reaction];
            
            if (count > 0) {
                if (countSpan.length) {
                    countSpan.text(count);
                } else {
                    btn.append(`<span class="corement-reaction-count">${count}</span>`);
                }
            } else {
                countSpan.remove();
            }
        });
    }
    
    /**
     * Oy sayılarını güncelle
     */
    function updateVoteCounts(commentId, counts) {
        const comment = $(`.corement-comment[data-comment-id="${commentId}"]`);
        const countSpan = comment.find('.corement-vote-count');
        
        countSpan.text(counts.total);
        
        // Renk güncellemesi
        countSpan.removeClass('positive negative');
        if (counts.total > 0) {
            countSpan.addClass('positive');
        } else if (counts.total < 0) {
            countSpan.addClass('negative');
        }
    }
    
    /**
     * Gönder butonunu sıfırla
     */
    function resetSubmitButton(submitBtn, btnText, btnLoading) {
        isSubmitting = false;
        submitBtn.prop('disabled', false);
        btnText.show();
        btnLoading.hide();
    }
    
    /**
     * Mesaj göster
     */
    function showMessage(message, type = 'info') {
        const messageDiv = $('<div>')
            .addClass(`corement-message corement-${type}`)
            .text(message)
            .css({
                'position': 'fixed',
                'top': '20px',
                'right': '20px',
                'padding': '15px 20px',
                'border-radius': '6px',
                'z-index': '9999',
                'max-width': '300px',
                'box-shadow': '0 4px 12px rgba(0,0,0,0.15)'
            });
        
        // Tip'e göre renk
        if (type === 'error') {
            messageDiv.css({
                'background': '#e74c3c',
                'color': 'white'
            });
        } else if (type === 'success') {
            messageDiv.css({
                'background': '#27ae60',
                'color': 'white'
            });
        } else {
            messageDiv.css({
                'background': '#3498db',
                'color': 'white'
            });
        }
        
        $('body').append(messageDiv);
        
        // 5 saniye sonra kaldır
        setTimeout(() => {
            messageDiv.fadeOut(() => messageDiv.remove());
        }, 5000);
        
        // Tıklayınca kaldır
        messageDiv.on('click', function() {
            $(this).fadeOut(() => $(this).remove());
        });
    }
    
    /**
     * Smooth scroll to comment
     */
    function scrollToComment(commentId) {
        const comment = $(`.corement-comment[data-comment-id="${commentId}"]`);
        if (comment.length) {
            $('html, body').animate({
                scrollTop: comment.offset().top - 100
            }, 500);
        }
    }
    
    /**
     * URL hash kontrolü (yorum linkine tıklanmışsa)
     */
    if (window.location.hash.startsWith('#comment-')) {
        const commentId = window.location.hash.replace('#comment-', '');
        setTimeout(() => scrollToComment(commentId), 500);
    }
    
})(jQuery);

