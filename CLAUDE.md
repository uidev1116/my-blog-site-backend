# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a blog site backend built with a-blog cms, a Japanese content management system. The project consists of a main CMS installation, custom themes, and plugins that extend functionality.

## Development Commands

### Initial Setup
```bash
npm run setup
```
Initializes git submodules, installs dependencies, and creates symbolic links for plugins.

### Code Quality
```bash
# Lint staged files (runs automatically on pre-commit)
npx lint-staged
```

## Architecture

### Main Components

**ablogcms/**: Core CMS installation
- PHP-based content management system
- Database configuration in `config.server.php`
- Configured to use MariaDB running in Docker

**ablogcms/themes/uidev/**: Main theme
- TypeScript/React-based frontend
- Vite build system with two entry points: `index.ts` (frontend) and `admin.ts` (admin panel)
- SCSS styling with organized structure (foundation, layout, object)
- Production build outputs to theme directory for CMS integration

**plugins/**: Custom plugins as git submodules
- `ApiPreview/`: Headless CMS preview functionality
- Plugins are symlinked to `ablogcms/extension/plugins/` during setup

**docker/**: Development environment
- MariaDB database service
- PHP/Apache web server with a-blog cms image

### Database

#### Configuration Files

**ablogcms/php/config/schema/db.schema.yaml**: Database schema configuration
- Defines the structure of the database tables

**ablogcms/php/config/schema/db.engine.yaml**: Database engine configuration
- Defines the engine of the database tables

**ablogcms/php/config/schema/db.index.yaml**: Database index configuration
- Defines the indexes of the database tables

#### Database Operations

@ablogcms/php/Services/Database: Database Access Layer
- Provides a unified interface for database operations
- Implements the Singleton pattern
- Uses PDO for database connectivity
- Provides a set of methods for database operations
- Provides a set of methods for database operations

@ablogcms/php/SQL: SQL Query Builder
- Provides a unified interface for SQL query building
- Uses Doctrine DBAL for SQL query building
- Provides a set of methods for SQL query building

#### Development Environment
Development uses MariaDB in Docker:
- Host: `db` (Docker service)
- Database: `db_myblog`
- User: `root`
- Password: `root`
- Port: 3306

### Build System

The theme uses Vite with:
- React for interactive components
- TypeScript for type safety
- SCSS for styling with Stylelint
- Code splitting for vendor libraries
- Manifest generation for CMS asset integration

## Development Environment

1. Start Docker services: `docker-compose -f docker/compose.yaml up -d`
2. Run initial setup: `npm run setup`
3. Navigate to theme directory: `cd ablogcms/themes/uidev`
4. Start development: `npm run dev`
5. Access CMS at: http://localhost:8080

## Git Workflow

- Uses Husky for pre-commit hooks
- lint-staged runs Prettier on JS/TS files in themes and tools
- Plugins are managed as git submodules
- Main branch: `master`
