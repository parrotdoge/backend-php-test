var app = new Vue({
    el: '#app',
    delimiters: ['[[',']]'],
    data: {
        todos: [],
        form: {
            todoDescription: null,
            error: null,
            success: null,
        },
        transitionName: ""
    },
    methods: {
        loadTodos() {
            var self = this;

            $.get('/todo/0/json', function(response) {
                self.todos = response.todos;
            });
        },
        getCompleteButtonClass(todo) {
            if (todo.completed == '1') {
                return 'glyphicon-ok';
            }

            return 'glyphicon-unchecked';
        },
        toggleStatus(todoId) {
            var self = this;

            $.post('/todo/togglestatus/' + todoId + '?api=1', function(response) {
                if (response.success) {
                    var index = self.todos.findIndex(todo => todo.id === todoId);
                    self.todos[index].completed = (self.todos[index].completed == "0" ? "1" : "0");
                }
            });
        },
        deleteTodo(todoId) {
            var self = this;

            $.post('/todo/delete/' + todoId + '?api=1', function(response) {
                if (response.success) {
                    var index = self.todos.findIndex(todo => todo.id === todoId);
                    self.todos.splice(index, 1);
                }
            });
        },
        addTodo() {
            var self = this;

            this.form.error = '';
            if (!this.form.todoDescription) {
                this.form.error = 'Please enter a description for your Todo';
                return;
            }

            var data = {
                'description': this.form.todoDescription
            }

            $.post('/todo/add', data, function(response) {
                if (response.success) {
                    // Push into the todos array to display
                    self.todos.push(response.todo);

                    // Reset the description input
                    self.form.todoDescription = null;

                    // Show success message
                    self.form.success = 'Todo added.';

                    // Then hide success message after delay
                    setTimeout(function() {
                        self.form.success = null;
                    }, 2000);

                    // Scrolls to bottom of the todo list after render
                    Vue.nextTick(function() {
                        window.scrollTo(0, document.body.scrollHeight);
                    })
                }
            });
        }
    },
    created() {
        this.loadTodos();
    },
    mounted() {
        var self = this;
        setTimeout(function() {
            // Activate the transitions only after the initial render
            self.transitionName = "slide"
        }, 100)
    }
})
