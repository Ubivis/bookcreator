# Markdown to PDF Book Generator

A PHP program for creating professional PDF books from Markdown files. The generator supports structured organization in acts and chapters, automatic table of contents, typographical improvements, and image integration.

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![PHP](https://img.shields.io/badge/php-%3E%3D7.4-green)

## Features

- Conversion of Markdown files to structured PDF books
- Organization in acts and chapters
- Automatic table of contents with bookmarks
- GitHub integration (public and private repositories)
- Local file upload
- Typographical improvements (quotation marks, em dashes, etc.)
- Flexible configuration via JSON metadata
- Custom act titles via JSON configuration
- Image integration for acts and within the text
- Various book formats (A4, A5, etc.)

## Installation

### Requirements

- PHP 7.4 or higher
- Composer
- Web server with PHP support (e.g., Apache, Nginx)

### Installation via Composer

1. Clone the repository or download as a ZIP file:
   ```bash
   git clone https://github.com/Ubivis/bookcreator.git
   cd bookcreator
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Create output directory and set permissions:
   ```bash
   mkdir -p output
   chmod 755 output
   ```

4. Configure your web server to point to the directory.

### Installation on Plesk

1. Create a new subdomain or use an existing one.
2. Upload the files to the server.
3. Make sure that `composer.json` is in the root directory.
4. Run the Composer command via the Plesk interface or SSH:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
5. Create an output directory and set the correct permissions:
   ```bash
   mkdir -p output
   chmod 755 output
   ```

## Usage

The generator can be used in two ways:

1. Via web browser (recommended)
2. Via command line (for advanced users)

### Web Browser Interface

Open the generator's URL in your browser and you'll get a simple interface with two tabs:

#### 1. File Upload

Select a local Markdown file from your computer and click "Generate PDF".

#### 2. GitHub Repository

Enter the URL of a GitHub repository and choose between two modes:

- **Single Markdown File**: If your book is stored in a single Markdown file.
- **Directory Structure**: If your book is organized in acts and chapters (recommended).

For private repositories, please enter a valid GitHub token.

## Directory Structure and Organization

### Recommended Directory Structure on GitHub

```
MyBook/
├── metadata.json         # Metadata and configuration
├── cover.jpg             # Cover image (optional)
├── Act1.png              # Act images (optional)
├── Act2.png
├── 1. Act/
│   ├── 01. Chapter.md
│   ├── 02. Chapter.md
│   └── images/           # Chapter images (optional)
│       └── scene1.jpg
├── 2. Act/
│   ├── 01. Chapter.md
│   └── ...
└── ...
```

### Markdown Structure

If you use a single Markdown file, it should be structured as follows:

```markdown
# Book Title
**Author:** Author's Name

## 1. Act
### 1. Chapter
Content of the first chapter...

### 2. Chapter
Content of the second chapter...

## 2. Act
### 3. Chapter
...
```

## Configuration with metadata.json

You can create a `metadata.json` file in the main directory of your book to configure various aspects of the PDF:

```json
{
  "title": "My Book",
  "author": "John Doe",
  "language": "en",
  "description": "An exciting story about...",
  "publisher": "Self-Published",
  "date": "2025-05-20",
  "cover_image": "cover.jpg",
  
  "format": "A5",
  "font": "DejaVuSerif",
  "font_size": 12,
  "margin_left": 20,
  "margin_right": 20,
  "margin_top": 25,
  "margin_bottom": 25,
  "hyphenate": true,
  
  "acts": [
    {
      "number": 1,
      "title": "The Beginning"
    },
    {
      "number": 2,
      "title": "The Turning Point"
    },
    {
      "number": 3,
      "title": "The Finale"
    }
  ]
}
```

### Available Settings

| Parameter | Description | Example |
|-----------|-------------|---------|
| title | Book title | "My Book" |
| author | Author's name | "John Doe" |
| language | Book language | "en" |
| description | Short description | "An exciting story..." |
| publisher | Publisher | "Self-Published" |
| date | Publication date | "2025-05-20" |
| cover_image | Path to cover image | "cover.jpg" |
| format | Page format | "A5", "A4" |
| font | Font | "DejaVuSerif" |
| font_size | Font size | 12 |
| margin_left | Left margin in mm | 20 |
| margin_right | Right margin in mm | 20 |
| margin_top | Top margin in mm | 25 |
| margin_bottom | Bottom margin in mm | 25 |
| hyphenate | Enable hyphenation | true/false |
| acts | Array of act definitions | See next section |

### Custom Act Titles

With the `acts` array, you can define custom titles for your acts. This allows you to use more meaningful titles than just "1. Act", "2. Act", etc.

```json
{
  "acts": [
    {
      "number": 1,
      "title": "The Mysterious Case"
    },
    {
      "number": 2,
      "title": "Conspiracies and Betrayal"
    },
    {
      "number": 3,
      "title": "Rescue and Revelation"
    }
  ]
}
```

- `number`: The act number (corresponds to the number in directory names like "1. Act")
- `title`: The title to use for this act

With this configuration, the acts will be displayed in the PDF as "1. Act: The Mysterious Case", "2. Act: Conspiracies and Betrayal", etc. This improves navigation and gives the reader a better overview of your book's structure.

The custom act titles are used in the PDF output in the following places:
- As a heading on the act title pages
- In the bookmarks for navigation
- In the table of contents

## Image Integration

### Act Images

For each act title page, you can include an image:

1. Name the images according to the scheme `Act1.png`, `Act2.png`, etc.
2. Place the images in one of the following locations:
   - In the main directory
   - In the respective act directory (e.g., `1. Act/Act1.png`)
   - In the `images/` directory

### Inline Images in Chapters

You can include images in your Markdown files using the standard Markdown syntax:

```markdown
![Image description](images/my-image.jpg)
```

Images should be stored relative to the chapter directory.

### Cover Image

A cover image can be specified in the metadata.json:

```json
{
  "cover_image": "cover.jpg"
}
```

## Tips and Best Practices

- **Structure**: Use a clear directory structure with numbered acts and chapters.
- **Images**: Keep images in an appropriate format and size (less than 1MB per image).
- **Consistency**: Use consistent formatting in all Markdown files.
- **Chapter Size**: Divide your text into manageable chapters.
- **Version Control**: When using GitHub, you can track the version history of your book.
- **Act Titles**: Use meaningful act titles via the JSON configuration to improve navigation.

## Troubleshooting

### Images Are Not Displayed

- Make sure the images are in the correct directories.
- Check the image paths in your Markdown files.
- Check the debug log at `output/debug_log.txt` for detailed information.
- Check if the image sizes are below the limits of your web server. Large images (>1MB) might cause problems.

### GitHub Access Problems

- Make sure your GitHub token has the correct permissions.
- For private repositories, you need at least `repo` permissions.

### PDF Generation Errors

- Check the PHP error logs of your server.
- Make sure all dependencies are correctly installed.
- Check if the `output` directory exists and is writable.
- If there are problems with act titles, check the format of your JSON file.

## Obtaining a GitHub Token

To access private GitHub repositories, you need a Personal Access Token:

1. Sign in to GitHub
2. Go to Settings → Developer settings → Personal access tokens → Tokens (classic)
3. Click "Generate new token"
4. Enter a name (e.g., "PDF Book Generator")
5. Select the "repo" scope
6. Click "Generate token"
7. Copy the generated token (it will only be shown once!)

## License

This project is licensed under the MIT License. See the LICENSE file for details.
