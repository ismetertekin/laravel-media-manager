# MediaManager Package for Laravel

A production-ready, reusable Laravel package with a modern, WordPress-like media manager modal. Built with Vue 3 and powered by Spatie Laravel MediaLibrary.

## Features

- **Spatie Integration**: Seamless backend file management using Spatie MediaLibrary.
- **Modern UI**: Clean, responsive Vue 3 components with light and dark mode support.
- **Virtual Folders**: Organize files into hierarchical folders (stored in database).
- **Image Processing**: Automatic conversions (thumb, medium, large) and WebP generation.
- **Infinite Scroll**: Smoothly browse large media collections.
- **Multi-select**: Support for single or multiple file selection with configurable limits.
- **Drag & Drop**: Effortless file uploads via drag-and-drop.
- **Theme-agnostic**: Self-contained CSS with custom properties that adapt to any admin theme.

## Installation

1. Add the package to your `composer.json` or require it locally during development:
   ```bash
   composer require yazilim360/media-manager
   ```

2. Publish the configuration and migrations:
   ```bash
   php artisan vendor:publish --tag=media-manager-config
   php artisan vendor:publish --tag=media-manager-migrations
   ```

3. Run the migrations:
   ```bash
   php artisan migrate
   ```

4. If you are developing locally, add the package assets to your `vite.config.js`:
   ```js
   import vue from '@vitejs/plugin-vue';
   // ...
   laravel({
       input: [
           'packages/yazilim360/media-manager/resources/js/media-manager.js',
           'packages/yazilim360/media-manager/resources/css/media-manager.css',
       ],
   }),
   vue(),
   ```

## Usage

### Blade Component
The easiest way to use the media manager is via the provided Blade component:

```blade
<x-media-picker 
    :multiple="true" 
    :max="5" 
    button-text="Select Images" 
    on-select="myCallbackFunction" 
/>
```

### JavaScript API
You can also open the media manager programmatically:

```js
window.MediaManager.open({
    multiple: true,
    max: 10,
    types: ['image', 'video'],
    onSelect: (files) => {
        console.log('Selected files:', files);
        // files is an array of objects: { id, url, name, size, ... }
    }
});
```

## Configuration
The `config/media-manager.php` file allows you to customize storage:

```php
return [
    'disk_path' => 'media-manager', // Path on disk
    'sidebar'   => true,             // Sidebar default visibility
    'default_view' => 'grid',        // 'grid' or 'list'
    'locale'    => 'en',             // en, tr
    // ...
];
```

## Features Deep-Dive
- **View Modes**: Toggle between Grid and List views via the toolbar.
- **Confirmations**: Powered by SweetAlert2 for a premium feel.
- **Move/Copy**: Organize your library with virtual move/copy operations.
- **Dark Mode**: Native support with theme persistence.
- **Localization**: Change `app()->getLocale()` to switch between EN and TR.
