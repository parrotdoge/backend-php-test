<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    $twig->addGlobal('user', $app['session']->get('user'));

    return $twig;
}));


$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html', [
        'readme' => file_get_contents('README.md'),
    ]);
});


$app->get('/login', function () use ($app) {
    return $app['twig']->render('login.html', array());
});


$app->post('/login', function (Request $request) use ($app) {

    $username = $request->get('username');
    $password = $request->get('password');

    // Do some validation
    $hasErrors = false;

    if (empty($username)) {
        $app['session']->getFlashBag()->add('loginErrors', 'Please enter a username.');
        $hasErrors = true;
    }

    if (empty($password)) {
        $app['session']->getFlashBag()->add('loginErrors', 'Please enter a password.');
        $hasErrors = true;
    }

    if ($hasErrors) {
        return $app->redirect('/login');
    }

    // Fetch the user by username
    $stmt = $app['db']->prepare("
        SELECT *
        FROM `users`
        WHERE `username` = ?
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetchAssociative();

    if (empty($user)) {
        $app['session']->getFlashBag()->add('loginErrors', 'Invalid username or password');
        return $app->redirect('/todo');
    }

    // Verify the password matches the hash
    if (password_verify($password, $user['password'])) {
        $app['session']->set('user', $user);
        return $app->redirect('/todo');
    } else {
        $app['session']->getFlashBag()->add('loginErrors', 'Invalid username or password');
        return $app->redirect('/todo');
    }

    return $app->redirect('/login');
});


$app->get('/logout', function () use ($app) {
    $app['session']->set('user', null);
    return $app->redirect('/');
});


$app->get('/todo/{id}', function ($id) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    if ($id) {
        $stmt = $app['db']->prepare("
            SELECT *
            FROM `todos`
            WHERE `id` = ?
        ");
        $stmt->execute([$id]);
        $todo = $stmt->fetchAssociative();

        return $app['twig']->render('todo.html', [
            'todo' => $todo,
        ]);
    } else {
        $stmt = $app['db']->prepare("
            SELECT *
            FROM `todos`
            WHERE `user_id` = ?
        ");
        $stmt->execute([$user['id']]);
        $todos = $stmt->fetchAll();

        return $app['twig']->render('todos.html', [
            'todos' => $todos,
        ]);
    }
})
->value('id', null);


$app->post('/todo/add', function (Request $request) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $user_id = $user['id'];
    $description = $request->get('description');

    // Check if description is empty
    if (empty($description)) {
        $app['session']->getFlashBag()->add('formErrors', 'Please enter a description for your Todo');
        return $app->redirect('/todo');
    }

    // Security issue: Use a prepared statement to prevent any SQL injection
    $app['db']->prepare("
        INSERT INTO `todos` (`user_id`, `description`)
        VALUES (?, ?)
    ")->execute([$user_id, $description]);

    return $app->redirect('/todo');
});


$app->post('/todo/delete/{id}', function ($id) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $app['db']->prepare("
        DELETE FROM `todos`
        WHERE `id` = ?
        AND `user_id` = ?
    ")->execute([$id, $user['id']]);

    return $app->redirect('/todo');
});


$app->post('/todo/togglestatus/{id}', function ($id) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $app['db']->prepare("
        UPDATE `todos`
        SET `completed` = 1 - `completed`
        WHERE `id` = ?
        AND `user_id` = ?
    ")->execute([$id, $user['id']]);

    return $app->redirect('/todo');
});
