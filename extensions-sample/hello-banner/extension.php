<?php
/**
 * Hello Banner - example Zinkly Social Lite extension.
 * Everything an extension does goes through Ext::on(<hook>, <function>). See EXTENSIONS.md for the list of hooks.
 */
$id = 'hello-banner';

// a card in the right column (returns HTML; always escape with e())
Ext::on('aside', function (?array $user) use ($id) {
    if (!$user && Ext::setting($id, 'show_guests', '1') !== '1') {
        return '';
    }
    $style = Ext::setting($id, 'style', 'violet');
    return '<section class="zl-card zl-side-card hb-card hb-' . e($style) . '"><h3>' . e(Ext::setting($id, 'title', 'Announcement')) . '</h3>'
        . '<p>' . nl2br(e(Ext::setting($id, 'message', ''))) . '</p></section>';
});

// its own stylesheet (files in the extension folder are served: css, js, images, fonts)
Ext::on('head', fn () => '<link rel="stylesheet" href="' . e(Ext::asset($id, 'assets/hello.css')) . '">');

// a page of its own, linked from the admin menu
Router::get('/admin/hello', function () use ($id) {
    View::render('pages/text', ['title' => 'Hello Banner', 'body' => 'This page comes from the Hello Banner extension. Message: ' . Ext::setting($id, 'message', '')]);
}, ['auth' => true, 'admin' => true]);
Ext::on('admin_nav', function (array $items) {
    $items['hello'] = ['admin/hello', 'sparkle', 'Hello Banner'];
    return $items;
});

// react to things that happen
Ext::on('post_created', function (int $postId, int $userId) {
    // for example: send it to another service, give points, ...
});
