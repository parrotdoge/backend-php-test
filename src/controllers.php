<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

const PAGE_SIZE = 5;

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


$app->get('/todo/{id}', function (Request $request, $id) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    if ($id) {
        $stmt = $app['db']->prepare("
            SELECT *
            FROM `todos`
            WHERE `id` = ?
            AND `user_id` = ?
        ");
        $stmt->execute([$id, $user['id']]);
        $todo = $stmt->fetchAssociative();

        if (empty($todo)) {
            return $app->redirect('/todo');
        }

        return $app['twig']->render('todo.html', [
            'todo' => $todo,
        ]);
    } else {

        // Get all the Todo ids to paginate
        $stmt = $app['db']->prepare("
            SELECT `id`
            FROM `todos`
            WHERE `user_id` = ?
            ORDER BY `id`
        ");
        $stmt->execute([$user['id']]);
        $todoIds = $stmt->fetchAll();
        $todoIds = array_column($todoIds, 'id');

        // Calculate the items and number of pages
        $totalItems = count($todoIds);
        $totalPages = ceil($totalItems / PAGE_SIZE);
        $totalPages = empty($totalPages) ? 1 : $totalPages;

        // Check if we have a specific page requested
        $page = $request->get('p');
        $page = empty($page) ? 1 : $page;
        $page = ($page > $totalPages) ? $totalPages : $page;

        // Work out the offset id
        $offsetId = $todoIds[($page - 1) * PAGE_SIZE];

        // Fetch the paged todos
        $stmt = $app['db']->prepare("
            SELECT *
            FROM `todos`
            WHERE `user_id` = ?
            AND `id` >= ?
            ORDER BY `id`
            LIMIT " . PAGE_SIZE . "
        ");
        $stmt->execute([$user['id'], $offsetId]);
        $todos = $stmt->fetchAll();

        // Pass the page data to the template
        $pageData = new stdClass;
        $pageData->total = $totalPages;
        $pageData->page = $page;

        return $app['twig']->render('todos.html', [
            'todos' => $todos,
            'pageData' => $pageData
        ]);
    }
})
->value('id', null);


$app->get('/todo/{id}/json', function ($id) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $stmt = $app['db']->prepare("
        SELECT *
        FROM `todos`
        WHERE `id` = ?
        AND `user_id` = ?
    ");
    $stmt->execute([$id, $user['id']]);
    $todo = $stmt->fetchAssociative();

    if (empty($todo)) {
        $todo = [];
    }

    return $app->json($todo);
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
    $stmt = $app['db']->prepare("
        INSERT INTO `todos` (`user_id`, `description`)
        VALUES (?, ?)
    ");
    $stmt->execute([$user_id, $description]);

    if ($stmt->rowCount() > 0) {
        $app['session']->getFlashBag()->add('confirmationMessages', 'Todo added');
    }

    return $app->redirect('/todo');
});


$app->post('/todo/delete/{id}', function ($id) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $stmt = $app['db']->prepare("
        DELETE FROM `todos`
        WHERE `id` = ?
        AND `user_id` = ?
    ");

    $stmt->execute([$id, $user['id']]);

    if ($stmt->rowCount() > 0) {
        $app['session']->getFlashBag()->add('confirmationMessages', 'Todo deleted');
    }

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
