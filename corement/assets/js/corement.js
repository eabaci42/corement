// Corement Yorum Sistemi Temel JS
// Emoji, oylama, medya yükleme, matematik sorusu için temel fonksiyonlar buraya eklenecek

document.addEventListener('DOMContentLoaded', function() {
    // Tema algılama ve class ekleme
    var container = document.querySelector('.corement-container');
    if (container) {
        var dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        container.classList.add(dark ? 'corement-dark' : 'corement-light');
    }
    document.querySelectorAll('.corement-emoji').forEach(function(el) {
        el.addEventListener('click', function() {
            var type = el.getAttribute('data-type');
            alert('Emoji seçildi: ' + type);
        });
    });
    document.querySelectorAll('.corement-upvote').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var cid = btn.getAttribute('data-commentid');
            alert('Upvote: ' + cid);
        });
    });
    document.querySelectorAll('.corement-downvote').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var cid = btn.getAttribute('data-commentid');
            alert('Downvote: ' + cid);
        });
    });
});
