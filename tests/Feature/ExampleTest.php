<?php

test('the root route redirects to the dashboard', function () {
    $this->get('/')->assertRedirect(route('dashboard'));
});

test('guests are redirected to login from the dashboard', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});
