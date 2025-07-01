<?php
// Corement - Yorum Listeleme ve Yanıt Sistemi (Taslak)
if (!defined('ABSPATH')) exit;

add_action('corement_render_comment_list', function() {
    global $post;
    $comments = get_comments(array(
        'post_id' => $post->ID,
        'status' => 'approve',
        'order' => 'DESC',
    ));
    echo '<div class="corement-comment-list">';
    if ($comments) {
        corement_render_comments($comments);
    } else {
        echo '<p>Henüz yorum yok. İlk yorumu siz yapın!</p>';
    }
    echo '</div>';
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.corement-reply-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var cid = btn.getAttribute('data-commentid');
                var form = document.getElementById('corement-reply-form-'+cid);
                if (form) form.style.display = (form.style.display === 'block' ? 'none' : 'block');
            });
        });
    });
    </script>
    <?php
});

function corement_render_comments($comments, $parent_id = 0, $depth = 0) {
    if ($depth > 2) return; // Maksimum 3 seviye
    foreach ($comments as $comment) {
        if ((int)$comment->comment_parent === (int)$parent_id) {
            $avatar = get_avatar($comment->comment_author_email, 48);
            echo '<div class="corement-comment" style="margin-left:'.($depth*30).'px">';
            echo $avatar;
            echo '<strong>' . esc_html($comment->comment_author) . '</strong> ';
            echo '<span class="corement-date">' . esc_html($comment->comment_date) . '</span>';
            // İçerik ve medya (HTML destekli)
            echo '<div class="corement-content">' . wp_kses_post($comment->comment_content) . '</div>';
            // Yanıtla butonu ve alt form
            echo '<button class="corement-reply-btn" data-commentid="'.$comment->comment_ID.'">Yanıtla</button>';
            echo '<div id="corement-reply-form-'.$comment->comment_ID.'" class="corement-reply-form" style="display:none;">';
            echo '<form method="post">';
            echo '<input type="hidden" name="corement_parent" value="'.$comment->comment_ID.'" />';
            echo '<input type="text" name="corement_name" placeholder="Adınız" required /> ';
            echo '<input type="email" name="corement_email" placeholder="E-posta" required /> ';
            echo '<textarea name="corement_comment" placeholder="Yanıtınız..." required></textarea> ';
            echo '<button type="submit">Yanıtı Gönder</button>';
            echo '</form>';
            echo '</div>';
            // Oylama ve emoji placeholder
            echo '<div class="corement-vote-emoji">';
            echo '<span class="corement-emoji" data-type="like">👍 <span class="corement-emoji-count">0</span></span> ';
            echo '<span class="corement-emoji" data-type="laugh">😂 <span class="corement-emoji-count">0</span></span> ';
            echo '<span class="corement-emoji" data-type="wow">😮 <span class="corement-emoji-count">0</span></span> ';
            echo '<span class="corement-emoji" data-type="sad">😢 <span class="corement-emoji-count">0</span></span> ';
            echo '<span class="corement-emoji" data-type="angry">😡 <span class="corement-emoji-count">0</span></span> ';
            echo '<span class="corement-vote">';
            echo '<button class="corement-upvote" data-commentid="'.$comment->comment_ID.'">▲</button> <span class="corement-vote-count">0</span> ';
            echo '<button class="corement-downvote" data-commentid="'.$comment->comment_ID.'">▼</button>';
            echo '</span>';
            echo '</div>';
            // Alt yorumlar
            corement_render_comments($comments, $comment->comment_ID, $depth+1);
            echo '</div>';
        }
    }
}
