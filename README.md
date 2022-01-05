**Движок API на PHP**
============
---

*Api Engine* - это удобная основа, для написания вашего API, в неё встроено много разных функций, которые позволяют незадумываться о их реализации.
<br>

Инициализация.
-----------------------------------------
___
Инициализация движка API очень легка, необходимо просто установить соединение с базой данных, создать экземлпяр класса API и добавить методы.

```php
<?php
    use Me\Korolevsky\Api\Api;
    use Me\Korolevsky\Api\DB\Servers;
    use Me\Korolevsky\Api\DB\Server as ServerDB;
    use Me\Korolevsky\Api\Utils\Response\Response;
    use Me\Korolevsky\Api\Utils\Response\OKResponse;

    class Server {
        public function __construct() {
            $server = new ServerDB($host, $user, $password, $dbname); // Подключаемся к базе
            $api = new Api(); // Создаем экземпляр класса API.
            $api->addMethod("test.method", function(ServerDB|Servers $servers, array $params): Response {
                return new Response(200, new OKResponse($params['test_param']));
            }, ['test_param']); // Добавляем метод `test.method` с обязательным параметром test_param.
            $api->processRequest("test.method", $server); // Запускаем обработку запроса.
        }
    }
?>
```
С помощью такого легкого кода можно добавить метод API и запустить его обработку.


Методы
-----------------------------------------
___
Разберемся конкретнее с работой методов, а именно с параметрами функции addMethod().<br><br>
Функция addMethod() имеет разные параметры, а именно:
```json
{
  "method": "Название метода.",
  "function": "Функция для обработки метода.",
  "params": "Обязательные параметры.",
  "limits": "Лимиты на метод [в секунду, в полчаса, в час].",
  "need_authorization": "Требуется ли авторизация пользователя.",
  "need_admin": "Требуются ли для метода админ-права."
}
```

Кастомные обработчики
-----------------------------------------
___
Из предыдущего раздела, можно было заметить такие параметры функции addMethod(), как `need_authorization` и `need_admin`.<br>
Давайте разберемся с ними конкретнее.
<br><br>
    1. `need_authorization`: проверка на авторизацию, в движке есть встроенная проверка на авторизацию по `access_token`.<br>Есть класс для работы с ключем авторизации, с помощью него статичными функциями можно `получить id пользователя`, `проверить авторизацию`, `создать новый access_token`. 
<br><br>
    2. `need_admin`: проверка на права-администратора, так как движок не знает как определять администратора, то ему необходимо передать функцию для проверки, а как это делать расскажем дальше.

Добавление / изменение кастомных функций:

Для изменения функции `need_authorization`, необходимо воспользоваться данной функцией в API:
```php
<?php
    use Me\Korolevsky\Api\Api;
    use Me\Korolevsky\Api\DB\Servers;
    use Me\Korolevsky\Api\DB\Server as ServerDB;

    class Server {
        public function __construct() {
            $api = new Api(); // Создаем экземпляр класса API.
            $api->setCustomNeedAuthorizationFunction(function(Servers|ServerDB $servers, string $param): bool {
                if($param == "my_secret_string") {
                    return true;
                } else {
                    return false;
                }
            }, 'x-vk', true); // Изменяем функцию авторизации, авторизация теперь будет через параметры Header, а именно через параметр x-vk.
        }
    }
?>
```

Добавление функции `need_admin`:

```php
<?php
    use Me\Korolevsky\Api\Api;
    use Me\Korolevsky\Api\DB\Servers;
    use Me\Korolevsky\Api\DB\Server as ServerDB;

    class Server {
        public function __construct() {
            $api = new Api(); // Создаем экземпляр класса API.
            $api->setNeedAdminFunction(function(Servers|ServerDB $servers, int $user_id): bool {
                if($user_id == 1) {
                    return true;
                } else {
                    return false;
                }
            }); // Добавляем функцию проверки прав администратора.
        }
    }
?>
```

Учтите, что обе функции должны иметь параметры **Server|Servers** и **int $user_id** / **string $param**. И обе должны возвращать значение **true**/**false** (bool).
<br><br>
Также бывает необходимость в добавлении функций, которые будут выполняться перед методом, например для сбора статистики, в этом случае нам поможет функция addAltFunction()

```php
<?php
    use Me\Korolevsky\Api\Api;
    use Me\Korolevsky\Api\DB\Servers;
    use Me\Korolevsky\Api\DB\Server as ServerDB;

    class Server {
        public function __construct() {
            $api = new Api(); // Создаем экземпляр класса API.
            $api->addAltFunction(function(Servers|ServerDB $servers, array $params) {
                $stats = $servers->dispense('stats');
                $stats['access_token'] = $params['access_token'];
                $stats['time'] = time();
                $servers->store($stats);
            }); // Добавляем функцию для сбора статистики.
        }
    }
?>
```
В данном случае функция не должна возвращать значения, поскольку они не будут использоваться.

Работа с базой данных
-----------------------------------------
___

В движке есть свой интерфейс для работы с базой данных, он похож на знаменитый [RedBeanPHP](http://redbeanphp.com/).
```php
<?php
    use Me\Korolevsky\Api\DB\Server as ServerDB;

    class Server {
        public function __construct() {
            $server = new ServerDB(
                    $host, $user, $password, $dbname, $port
                )
        }
    }
?>
```

Все функции в интерфейсы защищены от SQL-инъекций, поэтому вам не стоит заморачиваться над экранизацией символов.

Несколько баз данных
-----------------------------------------
В работе API может потребоваться несколько баз данных, например если у Вас они находятся на разных серверах и хранят разную информацию. Для этого нам на помощь придёт класс **Servers**.
```php
<?php
    use Me\Korolevsky\Api\DB\Servers;
    use Me\Korolevsky\Api\DB\Server as ServerDB;

    class Server {
        public function __construct() {
            $servers = new Servers();
            $servers->addServer(
                new ServerDB(
                    $host, $user, $password, $dbname, $port
                )
            );
            $servers->addServer(
                new ServerDB(
                    $host2, $user2, $password2, $dbname2, $port2
                )
            , 'north');
        }
    }
?>
```
При добавлении сервера, Вы можете назначить ему определенный ключ, чтобы было удобнее с ними работать.<br><br>

Для выбора определенного сервера, мы можем воспользоваться функцией **selectServer()**:
```php
$servers->selectServer('north');
```
Для того чтобы проверить соединение со всеми серверами воспользуйтесь функцией **isConnected()**:
```php 
$servers->isConnected();
```
Также можно проверить и один сервер.

Получение данных
-----------------------------------------

Если вам получить одну строку, по определенным параметрам это можно сделать с помощью **findOne()**.
```php
$server->findOne('table', 'WHERE `id` = ?', [ 1 ]);
```
Данная функция произведет поиск в таблице `table` по полю `id` со значением `1` и вернет первую найденную запись.
<br><br><br>
Если вам необходимо получить большое количество данных, то для этого есть функция **select()**
```php
$server->select("SELECT * FROM `table` WHERE `name` = ?", [ 'KIRILL' ]);
```
Данная функция произведет поиск в таблице `table` по полю `name` со значением `KIRILL` и вернет все найденные записи.

Добавление данных
-----------------------------------------
Для добавления данных есть две функции, а именно **dispense()** и **store()**. Первая функция создаст объект для добавления, а вторая уже добавит его в базу.
```php
$db = $server->dispense("table");
$db['admin'] = true;
$db['text'] = uniqid();
$server->store($db);
```

Изменение данных
-----------------------------------------
Функции **dispense()** и **findOne()** создают объект базы данных, который потом можно отправить на обработку в **store()**.
```php
$db = $server->findOne("table", "WHERE `name` = ?", [ 'KIRILL' ]);
$db['admin'] = true;
$server->store($db);
```
Данный код найдет в таблице `table` строку, у которой `name` = `KIRILL`, изменит значение поля `admin` на `true` и сохранит изменения в базе данных.

Удаление данных
-----------------------------------------
Также как и для изменения, нам необходим объект базы данных, который можно получить через функцию **findOne()**.
```php
$db = $server->findOne("table", "WHERE `name` = ?", [ 'KIRILL' ]);
$server->trash($db);
```
В данном примере, мы находим в таблице `table` строку, у которой `name` = `KIRILL` и удаляем её с помощью функции **trash()**.

IP
-----------------------------------------
___

Многие из нас скрываются за CloudFlare, но стоит учесть, что скрываясь за ним, наш сервер получает запросы от самого CloudFlare, а следовательно мы получаем и IP его серверов.<br>
Для того чтобы получить истинный IP адрес пользователя в движке есть класс **IP** с функцией **get()**.
```php
echo IP::get();
```
Данный код покажет настоящий IP адрес пользователя.

Многопоточность
-----------------------------------------
___
В API иногда требуется многопоточность, например для того, чтобы начать выполнять долгий поиск в одном методе, а после уже в другом выдать результат.
На помощь придет класс **Task**.<br>
Пример:
```php
<?php
    use Me\Korolevsky\Api\Utils\Tasks\Task;
    use Me\Korolevsky\Api\DB\Server as ServerDB;

    class Server {
        public function __construct() {
            $server = new ServerDB(
                    $host, $user, $password, $dbname, $port
           );
           
           $params = ['test' => 1]; // Параметры в основном потоке
           $task = new Task(function($servers, array $params) {
                     sleep(3);
                     return $params['test'];
           }, $server, $params); // Создаем новый поток со своей функцией и параметрами.
           $task->start(); // Запускаем работу потока.
           
           $result = $task->getResult(); // Получаем результат потока
           while($result == "in_progress") { // Пока поток работает, он возвращает "in_progress"
                echo "Данный поток работает отдельно от другого и может выполнять разные дейстивя\n";
                
                sleep(1);
                $result = $task->getResult();
           }
           
           var_dump($result); // Результат выполенния потока.
        }
    }
?>
```
Выполнив данный код, можно наглядно увидеть что потоки работают независимо друг от друга.

Ошибки
-----------------------------------------
При работе другого потока, в нем могут быть произойти ошибки, но как же узнать, что именно в нем пошло не так?<br>
Тут нам помогут коды ошибок:
```json
{
  "-1000": "Произошла ошибка при получении task_id.",
  "-2000": "Произошла ошибка при получении server_name.",
  "-3000": "Произошла ошибка при получении кода потока.",
  "-1": "Произошла ошибка при выполнении кода, также возвращает объект error, в котором подробно описана ошибка.",
  "0": "Неизвестная ошибка, возникшая при работе потока."
}
```

Предостережения
-----------------------------------------
1. Стоит учесть, что у функции переданной в поток **не должно** быть типов переменных и возвращаемое значение, поскольку работа в данном случае не протестирована.
2. В коде не может быть использован **use**, поэтому придется прописывать названия классов **вместе** с пространством имен.

Результат API
-----------------------------------------
___

В движок встроены свои ответы API, которыми необходимо пользоваться:
```php
new Response(200, new OKResponse('All okay!'));
```
Данный код создаст готовый **response**, с http-кодом 200 (все хорошо) и результатом "All okay!":
```json
{
  "ok": true,
  "response": "All okay!"
}
```
Ответ об ошибке:
```php
new Response(404, new ErroResponse(1, 'Not found.'));
```
Этот код выдаст http-ошибку, а именно с кодом 404 (не найдено) и response ответом:
```json
{
  "ok": false,
  "error": {
    "error_code": 1,
    "error_msg": "Not found."
  }
}
```

Но бывает так, что перед выдачей результата, ему надо ещё что-то добавить, для этого нам придет на помощь параметр `need_generation`.
```php
new Response(404, new ErroResponse(1, 'Not found.'), false);
```
Тогда после запуска функции **proccessRequest()**, она вернет нам **Response** ответ, с которым Вы можете поработать ещё.