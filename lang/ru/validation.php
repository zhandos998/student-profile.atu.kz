<?php

return [
    'required' => 'Поле ":attribute" обязательно для заполнения.',
    'string' => 'Поле ":attribute" должно быть строкой.',
    'email' => 'Поле ":attribute" должно быть корректным email-адресом.',
    'confirmed' => 'Подтверждение поля ":attribute" не совпадает.',
    'min' => [
        'string' => 'Поле ":attribute" должно содержать не меньше :min символов.',
    ],
    'max' => [
        'string' => 'Поле ":attribute" не должно превышать :max символов.',
    ],
    'unique' => 'Такое значение поля ":attribute" уже используется.',

    'attributes' => [
        'email' => 'Email или телефон',
        'login' => 'логин Платонуса',
        'password' => 'пароль',
        'name' => 'ФИО',
        'phone' => 'номер телефона',
    ],
];
