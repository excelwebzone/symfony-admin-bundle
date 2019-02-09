# SymfonyAdminBundle - Admin backend for Symfony projects

This bundle provides an admin interface with common classes and services.

Install the bundle with:

```
composer require excelwebzone/symfony-admin-bundle
```

## Configuration

You would need to create a service to user and cron-schedule repositories
(as seen in example) and point them to the relative service.

```yaml
# config/packages/symfony_admin.yaml
symfony_admin:
    services:
        user_repository: 'app.user_repository'
        cron_schedule_repository: 'app.cron_schedule_repository'

services:
    app.user_repository:
        class: App\Repository\UserRepository
        arguments:
            - '@doctrine'
            - '@security.token_storage'
            - '@security.encoder_factory'

    app.cron_schedule_repository:
        class: App\Repository\CronScheduleRepository
        arguments:
            - '@doctrine'
            - '@security.token_storage'
```

```yaml
# config/routes.yaml
symfony_admin:
    resource: '@SymfonyAdminBundle/Controller'
    type: annotation
```

## Usage

TODO..
