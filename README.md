# Bolt MVC Framework [![Latest Stable Version](https://poser.pugx.org/rashid2538/bolt-framework/v/stable)](https://packagist.org/packages/rashid2538/bolt-framework) [![Total Downloads](https://poser.pugx.org/rashid2538/bolt-framework/downloads)](https://packagist.org/packages/rashid2538/bolt-framework) [![Latest Unstable Version](https://poser.pugx.org/rashid2538/bolt-framework/v/unstable)](https://packagist.org/packages/rashid2538/bolt-framework) [![License](https://poser.pugx.org/rashid2538/bolt-framework/license)](https://packagist.org/packages/rashid2538/bolt-framework)

Yes, you read it write, this is another PHP MVC framework to create applications with ease and quickly.

## Idea
The objective behind the development of this framework was to reduce the time required to update the database models after database design changes. The database access layer is inspired by the Entity Framework Architecuter of ASP .NET MVC.

## Installation
```shell
composer require rashid2538/bolt-framework
```

## Pros
* There is no need to create any database model for the CRUD operations.
* Speed up the development time by focusing only on the core logic of your app.
* Built in support for third party renderers like TWIG, PHPTAL etc.
* One can easily create new plugins to extend the functionalities framework.
* Support for CLI access

## Cons
* You'll have to learn a new thing. That seems to be a PIA (:stuck_out_tongue_winking_eye:) for some folks.
* Can use only one database in an application.
* The database schema has to follow some naming conventions, not a big deal though.
* Still in Beta. Yes, be ready for surprises.
