# Craft Blitz

Default settings and configuration for Craft Blitz, providing sensible caching defaults and query string exclusion logic.

## Requirements

- PHP 8.2 or higher
- Craft CMS 4.4 or higher
- Blitz 4.3 or higher

## Installation

```bash
composer require lameco/craft-blitz:^1.1
```

The constraint matters: the tags `4.0.0`, `5.0.0` and `5.0.1` belong to an older, unrelated
implementation from 2024, and without a constraint Composer picks `5.0.1`.

