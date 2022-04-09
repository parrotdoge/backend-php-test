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


$app->match('/login', function (Request $request) use ($app) {
    $username = $request->get('username');
    $password = $request->get('password');

    if ($username) {
        $stmt = $app['db']->prepare("
            SELECT *
            FROM `users`
            WHERE `username` = ?
            AND `password` = ?
        ");
        $stmt->execute([$username, $password]);
        $user = $stmt->fetchAssociative();

        if ($user) {
            $app['session']->set('user', $user);
            return $app->redirect('/todo');
        }
    }

    return $app['twig']->render('login.html', array());
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


$app->match('/todo/delete/{id}', function ($id) use ($app) {

    $app['db']->prepare("
        DELETE FROM `todos`
        WHERE `id` = ?
    ")->execute([$id]);

    return $app->redirect('/todo');
});
