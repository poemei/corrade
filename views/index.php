<?php
// path: /user/modules/corrade/views/index.php
if (!theme::render('head', get_defined_vars())) {
    require APPROOT . '/views/inc/head.php';
}
?>
<div class="container my-3">
    <h1>Corrade Manager</h1>
    <p>Corrade agent administration is available to authorized administrators.</p>
</div>
<?php
if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}
