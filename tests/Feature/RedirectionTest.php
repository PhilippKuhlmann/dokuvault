<?php

test('the application redirect to login on default url for unauthenticated user', function () {
    $response = $this->get('/');
    $response->assertRedirect('/login');
});

test('the application redirect to login on customer url for unauthenticated user', function () {
    $response = $this->get('/mustermann');
    $response->assertRedirect('/login');
});

test('the application redirect to login on admin url for unauthenticated user', function () {
    $response = $this->get('/admin');
    $response->assertRedirect('/login');
});

test('the application redirect to login on profile url for unauthenticated user', function () {
    $response = $this->get('/profile');
    $response->assertRedirect('/login');
});

test('the application redirect to login on remotesearch url for unauthenticated user', function () {
    $response = $this->get('/remotesearch');
    $response->assertRedirect('/login');
});
