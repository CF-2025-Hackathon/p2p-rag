## 1. Install Laravel
1. ```php artisan sail:install```
1. Select: `mysql` and `postgres`
1. Copy `.env.example` to `.env`
1. ```./vendor/bin/sail up -d```
1. ```./vendor/bin/sail exec ollama ollama pull gemma3:1b```
1. ```./vendor/bin/sail exec ollama ollama pull nomic-embed-text```
1. ```./vendor/bin/sail artisan key:generate```
1. ```./vendor/bin/sail artisan migrate```
1. ```./vendor/bin/sail artisan serve```

## Shell
1. ```./vendor/bin/sail shell```

## Down/Close
1. ```./vendor/bin/sail down```

## Test Ollama
1. ```./vendor/bin/sail exec ollama ollama list```
1. ```./vendor/bin/sail exec ollama ollama run gemma3:1b "The sky is blue because of Rayleigh scattering"```

## Open WebUI
1. ```http://localhost:3001/```

## Open pgAdmin
1. ```http://localhost:5050/```

## Test:
```http://localhost/api/p2p```


## 2. Install Supabase
1. `https://supabase.com/docs/guides/local-development`

## 3. Connect Laravel with Supabase
1. ```docker network create my_network```
1. ```docker network connect my_network laravel-laravel.test-1```
1. ```docker network connect my_network supabase_db_supabase```
1. ```docker network connect my_network sail-pgadmin```
1. Open docker container supabase > supabase_db_supabase
1. ```psql -h 127.0.0.1 -U postgres -d postgres -W```
1. ```CREATE USER new_user WITH PASSWORD 'new_password';```
1. ```GRANT CONNECT ON DATABASE postgres TO new_user;```
1. ```GRANT SELECT ON ALL TABLES IN SCHEMA public TO new_user;```
1. `create policy "Allow public read access"
on site_pages
for select
to public
using (true);`
1. ```GRANT SELECT ON ALL TABLES IN SCHEMA public TO new_user;```
1. Run in `http://127.0.0.1:54323/project/default/sql/1` file: `dummy-data/supabase-db-setup.sql`
1. Open in `http://127.0.0.1:54323/project/default`
1. Go to `Table Editor`
1. Go to table `site_pages`, and import file: `dummy-data/site_pages_rows.csv`


## 4. RUN
1. Open Postman
1. Set: `Content-Type: application/json`
1. Set: `Accept: application/json`
1. `http://localhost/api/query?question=why&perPage=5`



<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
