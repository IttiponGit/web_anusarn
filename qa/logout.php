<?php
require_once __DIR__ . '/includes/bootstrap.php';
qa_logout_user();
header('Location: ' . qa_url('login.php'));
exit;
