# lekalo-php

Языковой адаптер [Lekalo](https://github.com/ichinya/lekalo) для PHP: понимание PHP-кода, типов и символов, привязка к исходникам, общие интеграции Mago и Testo.

- Протокол к ядру — существующий lekalo.target process/JSON protocol.
- Framework-пакеты (lekalo-php-laravel, lekalo-php-symfony) подключаются через versioned provider API — issue [#1](https://github.com/ichinya/lekalo-php/issues/1).
- Общий план отделения — [ichinya/lekalo#241](https://github.com/ichinya/lekalo/issues/241).

## Состав (stage 4, M2)

| Путь | Содержимое |
| --- | --- |
| `src/provider.php` | Контракт провайдеров `dev.lekalo.provider@0.1.0` (`lekalo/provider/v0.1.0`): закрытый реестр, детерминированные переговоры версий, стабильные коды отказов `provider-missing` / `provider-duplicate` / `provider-version-conflict`. |
| `src/kernel.php`, `src/{ai-lint,analyzer,strict-profile,scenario-map,type-*,operation-*,route-policy,route-map}.php` | Языковое ядро: протокол, декодер, канонический вывод, типы, операции, generic-HTTP route map, статический lint. Ядро не называет ни один фреймворк. |
| `mago-toolchain.lock.json` | Пин тулчейна Mago (языковая custody). |
| `tests/protocol.php` | Сюита протокола: 143 проверки, включая переговоры провайдеров с синтетическим фикстурным провайдером (критерий приёмки lekalo-php#1) и child-лег пустого реестра. |
| `tests/analyzer.php` | Сюита analyzer-шва: 61 проверка. |
| `main.php` | Source-режим без провайдера: `describe`/`scan`/типы/операции работают; framework-семейства отказывают типизированно (`provider-missing`). |

## Запуск

```sh
php -n tests/protocol.php   # 143 проверки протокола и провайдерного шва
php -n tests/analyzer.php   # 61 проверка analyzer/strict-profile
```

Сюита `lekalo-php-laravel` запускается из его репозитория и резолвит этот пакет через `../lekalo-php/src` (или переменную `LEKALO_PHP_SRC`).

Статус: код перенесён из `adapters/php-laravel` монорепы (PR ichinya/lekalo#243); на переходном этапе совместимый артефакт собирается в единый `adapter.php` в монорепе из закреплённых (vendored) выпусков этого пакета и framework-пакетов.
