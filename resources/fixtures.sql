INSERT INTO users (username, password) VALUES
('user1', '$2y$10$TJyaqtKCGGrqLlRDCA3KVu4hqMs265EMLs1gMHvJIpu4n1vBOQpni'),
('user2', '$2y$10$w5AEw7o2gxQLjMUGQktZCehZ27u.OrQlSBYvHc7aMvWQvkVKn2xra'),
('user3', '$2y$10$jakQQcP8QbdstFQ6woAAQO/empvlMUvtOVjq2IXdKMu1Q8GPAjf76');

INSERT INTO todos (user_id, description) VALUES
(1, 'Vivamus tempus'),
(1, 'lorem ac odio'),
(1, 'Ut congue odio'),
(1, 'Sodales finibus'),
(1, 'Accumsan nunc vitae'),
(2, 'Lorem ipsum'),
(2, 'In lacinia est'),
(2, 'Odio varius gravida');
