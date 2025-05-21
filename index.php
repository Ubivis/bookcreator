<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
/**
 * Markdown zu PDF Buchgenerator
 * 
 * Ein PHP-Programm, das Markdown-Dateien in strukturierte PDF-Bücher konvertiert
 * mit Unterstützung für Akte, Kapitel, Formatierung und Inhaltsverzeichnisse.
 */

// Composer Autoloader
require 'vendor/autoload.php';

use Parsedown;
use Github\Client as GitHubClient;
use Mpdf\Mpdf;

class BookGenerator {
    private $markdown;
    private $bookStructure;
    private $config;
    private $mpdf;
    
    /**
     * Konstruktor
     * 
     * @param array $config Konfigurationsoptionen
     */
    public function __construct(array $config = []) {
        // Standard-Konfiguration
        $this->config = array_merge([
            'format' => 'A4',
            'margin_left' => 20,
            'margin_right' => 20,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_header' => 5,
            'margin_footer' => 5,
            'auto_toc' => true,
            'hyphenate' => true,
            'default_font' => 'DejaVuSerif',
            'default_font_size' => 11,
        ], $config);
        
        // Initialisiere Parsedown für Markdown-Konvertierung
        $this->markdown = new Parsedown();
        
        // Initialisiere die PDF-Engine
        $this->initPdf();
    }
    
    /**
     * Lädt Metadaten aus einer JSON-Datei
     * 
     * @param string $dirPath Pfad zum Buchverzeichnis
     * @return array Metadaten (Titel, Autor, etc.)
     */
    private function loadMetadataFromJson($dirPath) {
        $metadataFiles = [
            $dirPath . '/metadata.json',
            $dirPath . '/meta.json',
            $dirPath . '/book.json',
            $dirPath . '/config.json'
        ];
        
        $metadata = [
            'title' => basename($dirPath), // Standardwert ist Verzeichnisname
            'author' => '',
            'language' => 'de',
            'description' => '',
            'publisher' => '',
            'date' => date('Y-m-d'),
            'cover_image' => '',
            'format' => $this->config['format'],
            'font' => $this->config['default_font'],
            'font_size' => $this->config['default_font_size']
        ];
        
        foreach ($metadataFiles as $file) {
            if (file_exists($file)) {
                $jsonData = json_decode(file_get_contents($file), true);
                if ($jsonData) {
                    // Gefundene Metadaten mit Standardwerten zusammenführen
                    $metadata = array_merge($metadata, $jsonData);
                    
                    // Konfiguration aktualisieren, falls in der JSON vorhanden
                    if (isset($jsonData['format'])) $this->config['format'] = $jsonData['format'];
                    if (isset($jsonData['font'])) $this->config['default_font'] = $jsonData['font'];
                    if (isset($jsonData['font_size'])) $this->config['default_font_size'] = $jsonData['font_size'];
                    if (isset($jsonData['margin_left'])) $this->config['margin_left'] = $jsonData['margin_left'];
                    if (isset($jsonData['margin_right'])) $this->config['margin_right'] = $jsonData['margin_right'];
                    if (isset($jsonData['margin_top'])) $this->config['margin_top'] = $jsonData['margin_top'];
                    if (isset($jsonData['margin_bottom'])) $this->config['margin_bottom'] = $jsonData['margin_bottom'];
                    if (isset($jsonData['hyphenate'])) $this->config['hyphenate'] = $jsonData['hyphenate'];
                    if (isset($jsonData['acts'])) {
                        $metadata['act_titles'] = $jsonData['acts'];
                    }

                    break; // Beenden nach der ersten gefundenen Metadaten-Datei
                }
            }
        }
        
        return $metadata;
    }

    /**
     * Lädt Metadaten aus einer GitHub JSON-Datei
     * 
     * @param object $client GitHub-Client
     * @param string $username GitHub-Benutzername
     * @param string $repository Repository-Name
     * @param string $dirPath Pfad innerhalb des Repositories
     * @param string $branch Branch-Name
     * @return array Metadaten (Titel, Autor, etc.)
     */
    private function loadMetadataFromGitHub($client, $username, $repository, $dirPath, $branch) {
        $metadataFiles = [
            'metadata.json',
            'meta.json',
            'book.json',
            'config.json'
        ];
        
        $metadata = [
            'title' => basename($dirPath ?: $repository), // Standardwert
            'author' => '',
            'language' => 'de',
            'description' => '',
            'publisher' => '',
            'date' => date('Y-m-d'),
            'cover_image' => '',
            'format' => $this->config['format'],
            'font' => $this->config['default_font'],
            'font_size' => $this->config['default_font_size'],
            'acts' => [] // Leeres Array für Akte initialisieren
        ];
        
        foreach ($metadataFiles as $file) {
            $metaPath = ($dirPath ? "$dirPath/" : "") . $file;
            try {
                $jsonContent = $client->api('repo')->contents()->download(
                    $username, 
                    $repository, 
                    $metaPath, 
                    $branch
                );
                
                $jsonData = json_decode($jsonContent, true);
                if ($jsonData) {
                    // Debug-Ausgabe der geladenen JSON
                    file_put_contents('output/loaded_json.txt', print_r($jsonData, true));
                    
                    // Gefundene Metadaten mit Standardwerten zusammenführen
                    $metadata = array_merge($metadata, $jsonData);
                    
                    // Konfiguration aktualisieren, falls in der JSON vorhanden
                    if (isset($jsonData['format'])) $this->config['format'] = $jsonData['format'];
                    if (isset($jsonData['font'])) $this->config['default_font'] = $jsonData['font'];
                    if (isset($jsonData['font_size'])) $this->config['default_font_size'] = $jsonData['font_size'];
                    if (isset($jsonData['margin_left'])) $this->config['margin_left'] = $jsonData['margin_left'];
                    if (isset($jsonData['margin_right'])) $this->config['margin_right'] = $jsonData['margin_right'];
                    if (isset($jsonData['margin_top'])) $this->config['margin_top'] = $jsonData['margin_top'];
                    if (isset($jsonData['margin_bottom'])) $this->config['margin_bottom'] = $jsonData['margin_bottom'];
                    if (isset($jsonData['hyphenate'])) $this->config['hyphenate'] = $jsonData['hyphenate'];
                    
                    // PDF neu initialisieren mit aktualisierten Einstellungen
                    $this->initPdf();
                    
                    break; // Beenden nach der ersten gefundenen Metadaten-Datei
                }
            } catch (Exception $e) {
                // Keine Metadaten-Datei gefunden - weitermachen mit dem nächsten Muster
                continue;
            }
        }
        
        // Debug-Ausgabe der gesamten Metadaten
        file_put_contents('output/metadata_debug.txt', print_r($metadata, true));
        
        return $metadata;
    }

    /**
     * Initialisiert die PDF-Engine mit den Konfigurationsoptionen
     */
    private function initPdf() {
        // Konfiguration für mPDF vorbereiten
        $mpdfConfig = [
            'format' => $this->config['format'],
            'margin_left' => $this->config['margin_left'],
            'margin_right' => $this->config['margin_right'],
            'margin_top' => $this->config['margin_top'],
            'margin_bottom' => $this->config['margin_bottom'],
            'margin_header' => $this->config['margin_header'],
            'margin_footer' => $this->config['margin_footer'],
            'default_font' => $this->config['default_font'],
            'default_font_size' => $this->config['default_font_size'],
        ];
        
        // Wenn Silbentrennung aktiviert ist, füge sie zur Konfiguration hinzu
        if ($this->config['hyphenate']) {
            $mpdfConfig['hyphenation'] = true;
        }
        
        // Initialisiere mPDF mit der Konfiguration
        $this->mpdf = new Mpdf($mpdfConfig);
    }
    
    /**
     * Lädt Markdown-Inhalt aus einer Datei
     * 
     * @param string $filePath Pfad zur Markdown-Datei
     * @return bool Erfolg
     */
    public function loadFromFile($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("Datei nicht gefunden: $filePath");
        }
        
        $content = file_get_contents($filePath);
        return $this->loadFromString($content);
    }
    
    /**
     * Lädt Markdown-Inhalt aus einem Markdown-String
     * 
     * @param string $markdown Markdown-Inhalt
     * @return bool Erfolg
     */
    public function loadFromString($markdown) {
        $this->bookStructure = $this->parseBookStructure($markdown);
        return !empty($this->bookStructure);
    }
    
    /**
     * Lädt Markdown-Inhalt aus einem GitHub-Repository
     * 
     * @param string $username GitHub-Benutzername
     * @param string $repository Repository-Name
     * @param string $branch Branch-Name (standardmäßig 'main')
     * @param string $path Pfad innerhalb des Repositories
     * @param string $token GitHub API-Token für private Repositories
     * @return bool Erfolg
     */
    public function loadFromGitHub($username, $repository, $branch = 'main', $path = '', $token = null) {
        $client = new GitHubClient();
        
        // Authentifizierung für private Repositories
        if ($token) {
            $client->authenticate($token, null, GitHubClient::AUTH_ACCESS_TOKEN);
        }
        
        try {
            // Repository-Inhalt abrufen
            $content = $client->api('repo')->contents()->download($username, $repository, $path, $branch);
            return $this->loadFromString($content);
        } catch (Exception $e) {
            throw new Exception("Fehler beim Abrufen des GitHub-Inhalts: " . $e->getMessage());
        }
    }

    /**
     * Verbesserte Funktion zum Herunterladen von Dateien aus GitHub
     * Umgeht die API-Einschränkungen für große Dateien
     * 
     * @param object $client GitHub-Client (kann null sein, wenn Token separat übergeben wird)
     * @param string $username GitHub-Benutzername
     * @param string $repository Repository-Name
     * @param string $path Pfad zur Datei im Repository
     * @param string $branch Branch-Name
     * @param string $token GitHub API-Token (optional, wenn $client bereits authentifiziert ist)
     * @param string $outputPath Lokaler Pfad zum Speichern der Datei (optional)
     * @return string|bool Dateinhalt als String oder true, wenn in $outputPath gespeichert wurde
     */
    private function downloadLargeFileFromGitHub($client, $username, $repository, $path, $branch = 'main', $token = null, $outputPath = null) {
        // Debug-Ausgabe
        file_put_contents('output/download_debug.txt', "Versuche großen Download: $path\n", FILE_APPEND);
        
        try {
            // Zuerst den Standard-API-Ansatz versuchen
            if ($client) {
                try {
                    $content = $client->api('repo')->contents()->download(
                        $username, 
                        $repository, 
                        $path, 
                        $branch
                    );
                    
                    // Wenn erfolgreich und outputPath angegeben, Datei speichern
                    if ($outputPath) {
                        // Verzeichnispfad erstellen, falls er nicht existiert
                        $outputDir = dirname($outputPath);
                        if (!file_exists($outputDir) && $outputDir !== '.') {
                            mkdir($outputDir, 0755, true);
                        }
                        
                        file_put_contents($outputPath, $content);
                        return true;
                    }
                    
                    return $content;
                } catch (Exception $e) {
                    // Fehler protokollieren, aber weitermachen mit alternativem Ansatz
                    file_put_contents('output/download_debug.txt', "API-Download fehlgeschlagen: " . $e->getMessage() . "\nVersuche raw Download...\n", FILE_APPEND);
                }
            }
            
            // Falls der erste Ansatz fehlschlägt oder kein Client übergeben wurde,
            // direkten Download von raw.githubusercontent.com versuchen
            
            // Pfad für die URL richtig codieren - Leerzeichen ersetzen
            $encodedPath = str_replace(' ', '%20', $path);
            
            // URL zur Raw-Datei erstellen
            $rawUrl = "https://raw.githubusercontent.com/$username/$repository/$branch/$encodedPath";
            
            // Debug-Ausgabe
            file_put_contents('output/download_debug.txt', "Raw URL: $rawUrl\n", FILE_APPEND);
            
            // Kontext-Optionen erstellen
            $contextOptions = [
                'http' => [
                    'timeout' => 30, // 30 Sekunden Timeout
                    'header' => "User-Agent: PHP GitHub Downloader\r\n"
                ]
            ];
            
            // Wenn ein Token übergeben wurde, es zum Header hinzufügen
            if ($token) {
                $contextOptions['http']['header'] .= "Authorization: token $token\r\n";
            }
            
            $context = stream_context_create($contextOptions);
            
            // Datei herunterladen
            $content = @file_get_contents($rawUrl, false, $context);
            
            if ($content === false) {
                // Wenn das fehlschlägt, versuchen wir es mit urlencode für den gesamten Pfad
                $fullyEncodedPath = implode('/', array_map('urlencode', explode('/', $path)));
                $rawUrl = "https://raw.githubusercontent.com/$username/$repository/$branch/$fullyEncodedPath";
                
                file_put_contents('output/download_debug.txt', "Erster Raw-Download fehlgeschlagen, versuche mit vollständig kodiertem Pfad: $rawUrl\n", FILE_APPEND);
                
                $content = @file_get_contents($rawUrl, false, $context);
                
                if ($content === false) {
                    throw new Exception("Konnte Datei nicht über raw.githubusercontent.com herunterladen: $rawUrl");
                }
            }
            
            // Wenn outputPath angegeben, Datei speichern
            if ($outputPath) {
                // Verzeichnispfad erstellen, falls er nicht existiert
                $outputDir = dirname($outputPath);
                if (!file_exists($outputDir) && $outputDir !== '.') {
                    mkdir($outputDir, 0755, true);
                }
                
                file_put_contents($outputPath, $content);
                return true;
            }
            
            return $content;
        } catch (Exception $e) {
            file_put_contents('output/download_debug.txt', "Download fehlgeschlagen: " . $e->getMessage() . "\n", FILE_APPEND);
            throw $e; // Fehler weiterleiten
        }
    }

    
    /**
     * Lädt Markdown-Dateien aus einer GitHub-Verzeichnisstruktur
     * 
     * @param string $username GitHub-Benutzername
     * @param string $repository Repository-Name
     * @param string $dirPath Pfad innerhalb des Repositories
     * @param string $branch Branch-Name (standardmäßig 'main')
     * @param string $token GitHub API-Token für private Repositories
     * @return bool Erfolg
     */
    public function loadFromGitHubDirectory($username, $repository, $dirPath = '', $branch = 'main', $token = null) {
        $client = new GitHubClient();
        
        // Authentifizierung für private Repositories
        if ($token) {
            $client->authenticate($token, null, GitHubClient::AUTH_ACCESS_TOKEN);
        }
        
        try {
            // Metadaten aus JSON laden
            $metadata = $this->loadMetadataFromGitHub($client, $username, $repository, $dirPath, $branch);
            
            // Buch-Metadaten initialisieren
            $bookTitle = $metadata['title'];
            $author = $metadata['author'];
            $markdownContent = "# $bookTitle\n";
            
            if (!empty($author)) {
                $markdownContent .= "**Autor:** $author\n\n";
            }
            
            // Verzeichnisinhalt abrufen
            $dirContent = $client->api('repo')->contents()->show($username, $repository, $dirPath, $branch);
            
            // Akt-Verzeichnisse finden und sortieren
            $actDirs = [];
            foreach ($dirContent as $item) {
                if ($item['type'] === 'dir' && preg_match('/\d+\. Akt$/', $item['name'])) {
                    $actDirs[] = $item;
                }
            }
            
            // Natürliche Sortierung nach Aktnummer
            usort($actDirs, function($a, $b) {
                preg_match('/(\d+)\. Akt$/', $a['name'], $matchesA);
                preg_match('/(\d+)\. Akt$/', $b['name'], $matchesB);
                
                $numA = isset($matchesA[1]) ? intval($matchesA[1]) : 0;
                $numB = isset($matchesB[1]) ? intval($matchesB[1]) : 0;
                
                return $numA - $numB;
            });
            
            // Akt-Bilder herunterladen (falls vorhanden)
            $actIndex = 1;
            foreach ($actDirs as $actDir) {
                // Prüfen, ob ein Aktbild existiert (in verschiedenen Formaten)
                $formats = ['png', 'jpg', 'jpeg'];
                foreach ($formats as $format) {
                    $imagePath = ($dirPath ? "$dirPath/" : "") . "{$actDir['name']}/Akt$actIndex.$format";
                    
                    try {
                        // Verbesserte Download-Methode für große Bilder verwenden
                        $outputPath = "output/Akt$actIndex.$format";
                        $success = $this->downloadLargeFileFromGitHub(
                            $client, 
                            $username, 
                            $repository, 
                            $imagePath, 
                            $branch, 
                            $token, 
                            $outputPath
                        );
                        
                        if ($success) {
                            file_put_contents('output/download_debug.txt', "Bild erfolgreich heruntergeladen: $imagePath\n", FILE_APPEND);
                            break; // Beenden, wenn ein Bild gefunden wurde
                        }
                    } catch (Exception $e) {
                        // Alternative Pfade ausprobieren
                        try {
                            // Versuchen, im Hauptverzeichnis des Akts zu suchen
                            $altImagePath = ($dirPath ? "$dirPath/" : "") . "Akt$actIndex.$format";
                            
                            // Verbesserte Download-Methode für große Bilder verwenden
                            $outputPath = "output/Akt$actIndex.$format";
                            $success = $this->downloadLargeFileFromGitHub(
                                $client, 
                                $username, 
                                $repository, 
                                $altImagePath, 
                                $branch, 
                                $token, 
                                $outputPath
                            );
                            
                            if ($success) {
                                file_put_contents('output/download_debug.txt', "Bild erfolgreich heruntergeladen (aus alt. Pfad): $altImagePath\n", FILE_APPEND);
                                break; // Beenden, wenn ein Bild gefunden wurde
                            }
                        } catch (Exception $e2) {
                            // Bild nicht gefunden - normal weitermachen
                        }
                    }
                }
                $actIndex++;
            }
            
            // Jeden Akt verarbeiten
            foreach ($actDirs as $actDir) {
                $actName = $actDir['name'];
                $markdownContent .= "\n## $actName\n";
                
                // Kapitel-Dateien im Akt-Verzeichnis finden
                $actPath = $dirPath ? "$dirPath/{$actDir['name']}" : $actDir['name'];
                $actContent = $client->api('repo')->contents()->show($username, $repository, $actPath, $branch);
                
                // Kapitel-Dateien finden und sortieren
                $chapterFiles = [];
                foreach ($actContent as $item) {
                    if ($item['type'] === 'file' && pathinfo($item['name'], PATHINFO_EXTENSION) === 'md') {
                        $chapterFiles[] = $item;
                    }
                    
                    // Bilder im Aktverzeichnis herunterladen (für Inline-Bilder)
                    if ($item['type'] === 'file' && in_array(pathinfo($item['name'], PATHINFO_EXTENSION), ['png', 'jpg', 'jpeg', 'gif'])) {
                        try {
                            // Verbesserte Download-Methode für große Bilder verwenden
                            $outputPath = "output/" . $item['path'];
                            
                            // Verzeichnispfad erstellen, falls er nicht existiert
                            $outputDir = dirname($outputPath);
                            if (!file_exists($outputDir) && $outputDir != "output/") {
                                mkdir($outputDir, 0755, true);
                            }
                            
                            $success = $this->downloadLargeFileFromGitHub(
                                $client, 
                                $username, 
                                $repository, 
                                $item['path'], 
                                $branch, 
                                $token, 
                                $outputPath
                            );
                            
                            if ($success) {
                                file_put_contents('output/download_debug.txt', "Inline-Bild erfolgreich heruntergeladen: " . $item['path'] . "\n", FILE_APPEND);
                            }
                        } catch (Exception $e) {
                            file_put_contents('output/download_debug.txt', "Fehler beim Herunterladen von Inline-Bild: " . $e->getMessage() . "\n", FILE_APPEND);
                            // Ignorieren, wenn ein Bild nicht heruntergeladen werden kann
                        }
                    }
                }
                
                // Natürliche Sortierung nach Kapitelnummer
                usort($chapterFiles, function($a, $b) {
                    preg_match('/(\d+)\. Kapitel/', $a['name'], $matchesA);
                    preg_match('/(\d+)\. Kapitel/', $b['name'], $matchesB);
                    
                    $numA = isset($matchesA[1]) ? intval($matchesA[1]) : 0;
                    $numB = isset($matchesB[1]) ? intval($matchesB[1]) : 0;
                    
                    return $numA - $numB;
                });
                
                // Jedes Kapitel verarbeiten
                foreach ($chapterFiles as $chapterFile) {
                    $chapterName = pathinfo($chapterFile['name'], PATHINFO_FILENAME);
                    $markdownContent .= "\n### $chapterName\n";
                    
                    // Kapitelinhalt laden
                    $chapterContent = $this->downloadLargeFileFromGitHub(
                        $client,
                        $username, 
                        $repository, 
                        $chapterFile['path'], 
                        $branch,
                        $token
                    );

                    // Bildpfade im Markdown korrigieren
                    $chapterContent = $this->fixMarkdownImagePaths($chapterContent, $actPath);
                    
                    $markdownContent .= $chapterContent . "\n";
                }
            }
            
            // Mit der generierten Markdown-Datei fortfahren
            $success = $this->loadFromString($markdownContent);
            
            // Debug-Ausgabe des gesamten Markdown-Inhalts
            file_put_contents('output/debug_markdown.md', $markdownContent);
            
            // Metadaten in die Buchstruktur einbinden
            if ($success) {
                // Wichtig: Wir merken uns die Aktnamen aus den Metadaten separat
                $customActTitles = $metadata['acts'] ?? [];
                
                // Standardfelder übernehmen
                foreach ($metadata as $key => $value) {
                    if ($key != 'acts') {  // Acts gesondert behandeln
                        $this->bookStructure[$key] = $value;
                    }
                }
                
                // Explizit die acts-Metadaten setzen
                if (!empty($customActTitles)) {
                    $this->bookStructure['customActTitles'] = $customActTitles;
                    
                    // Debug-Ausgabe
                    file_put_contents('output/act_titles_debug.txt', "Gespeicherte Akttitel:\n" . print_r($customActTitles, true));
                }
            }
            
            return $success;
        } catch (Exception $e) {
            throw new Exception("Fehler beim Abrufen des GitHub-Inhalts: " . $e->getMessage());
        }
    }

    /**
     * Korrigiert Bildpfade in Markdown-Inhalt
     * 
     * @param string $markdown Markdown-Text
     * @param string $basePath Basispfad für relative Bilder
     * @return string Korrigierter Markdown-Text
     */
    private function fixMarkdownImagePaths($markdown, $basePath) {
        // Regulärer Ausdruck für Markdown-Bilder: ![Text](Pfad)
        $pattern = '/!\[(.*?)\]\(([^)]+)\)/';
        
        return preg_replace_callback($pattern, function($matches) use ($basePath) {
            $altText = $matches[1];
            $imagePath = $matches[2];
            
            // Nur relative Pfade korrigieren
            if (strpos($imagePath, 'http') !== 0) {
                // Pfad mit Basispfad ergänzen (für späteres Laden aus dem Output-Verzeichnis)
                $newPath = "output/" . $basePath . "/" . $imagePath;
                return "![$altText]($newPath)";
            }
            
            return $matches[0]; // URL unverändert zurückgeben
        }, $markdown);
    }

    /**
     * Korrigiert HTML-Bildpfade vor dem Schreiben ins PDF
     * 
     * @param string $html Das HTML mit Bildtags
     * @return string Korrigiertes HTML
     */
    private function fixImagePaths($html) {
        // Regulärer Ausdruck für HTML-Bilder: <img src="...">
        $pattern = '/<img\s+[^>]*?src=["\']([^"\']+)["\'][^>]*?>/i';
        
        return preg_replace_callback($pattern, function($matches) {
            $srcPath = $matches[1];
            
            // Prüfen, ob der Pfad auf ein existierendes Bild verweist
            if (file_exists($srcPath)) {
                // Pfad ist korrekt, nichts ändern
                return $matches[0];
            }
            
            // Versuchen, das Bild im Output-Verzeichnis zu finden (wenn es aus GitHub stammt)
            $outputPath = $srcPath;
            if (strpos($srcPath, 'output/') !== 0) {
                $outputPath = 'output/' . $srcPath;
            }
            
            if (file_exists($outputPath)) {
                // Pfad korrigieren
                return str_replace('src="' . $srcPath . '"', 'src="' . $outputPath . '"', $matches[0]);
            }
            
            // Bild nicht gefunden, ursprüngliches Tag zurückgeben
            return $matches[0];
        }, $html);
    }
    
    /**
     * Parst die Buchstruktur aus Markdown
     * 
     * @param string $markdown Markdown-Inhalt
     * @return array Strukturierte Akte und Kapitel
     */
    private function parseBookStructure($markdown) {
        $structure = [
            'title' => '',
            'author' => '',
            'acts' => []
        ];
        
        // Zeilen in Array aufteilen
        $lines = explode("\n", $markdown);
        
        $currentAct = null;
        $currentChapter = null;
        $buffer = '';
        $inContentMode = false;
        
        foreach ($lines as $line) {
            // Buchtitel (H1)
            if (preg_match('/^# (.+)$/', $line, $matches)) {
                $structure['title'] = trim($matches[1]);
                continue;
            }
            
            // Autor (Meta-Information nach Titel)
            if (empty($structure['author']) && preg_match('/^\*\*Autor:\*\* (.+)$/', $line, $matches)) {
                $structure['author'] = trim($matches[1]);
                continue;
            }
            
            // Akt (H2)
            if (preg_match('/^## (.+)$/', $line, $matches)) {
                // Vorherigen Akt abschließen, falls vorhanden
                if ($currentAct !== null && $currentChapter !== null) {
                    $structure['acts'][$currentAct]['chapters'][$currentChapter]['content'] = trim($buffer);
                    $buffer = '';
                }
                
                $currentAct = trim($matches[1]);
                $currentChapter = null;
                $structure['acts'][$currentAct] = [
                    'title' => $currentAct,
                    'chapters' => []
                ];
                $inContentMode = false;
                continue;
            }
            
            // Kapitel (H3)
            if (preg_match('/^### (.+)$/', $line, $matches)) {
                // Vorheriges Kapitel abschließen, falls vorhanden
                if ($currentChapter !== null) {
                    $structure['acts'][$currentAct]['chapters'][$currentChapter]['content'] = trim($buffer);
                    $buffer = '';
                }
                
                $currentChapter = trim($matches[1]);
                $structure['acts'][$currentAct]['chapters'][$currentChapter] = [
                    'title' => $currentChapter,
                    'content' => ''
                ];
                $inContentMode = true;
                continue;
            }
            
            // Inhalt zum aktuellen Kapitel hinzufügen, aber nur wenn ein Kapitel aktiv ist
            if ($inContentMode && $currentAct !== null && $currentChapter !== null) {
                $buffer .= $line . "\n";
            }
        }
        
        // Letztes Kapitel abschließen
        if ($currentAct !== null && $currentChapter !== null) {
            $structure['acts'][$currentAct]['chapters'][$currentChapter]['content'] = trim($buffer);
        }
        
        return $structure;
    }

    
    /**
     * Generiert das PDF mit der aktuellen Buchstruktur
     * 
     * @param string $outputPath Pfad zum Speichern des PDFs
     * @return bool Erfolg
     */
    public function generatePdf($outputPath = 'book.pdf') {
        if (empty($this->bookStructure)) {
            throw new Exception("Keine Buchstruktur zum Generieren vorhanden");
        }
        
        // Debug-Ausgabe erstellen
        file_put_contents('output/debug_log.txt', "Generating PDF: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        file_put_contents('output/debug_structure.txt', print_r($this->bookStructure, true));
        
        // Titel und Metadaten setzen
        $this->mpdf->SetTitle($this->bookStructure['title']);
        if (!empty($this->bookStructure['author'])) {
            $this->mpdf->SetAuthor($this->bookStructure['author']);
        }
        
        // Titelseite erstellen
        $this->createTitlePage();
        
        // Inhaltsverzeichnis vorbereiten, wenn aktiviert
        if ($this->config['auto_toc']) {
            $this->mpdf->TOCpagebreak(
                'Inhaltsverzeichnis', 
                1, 
                1, 
                1, 
                '', 
                '', 
                '', 
                '', 
                '', 
                '', 
                'book-toc'
            );
        }
        
        // Buchinhalt hinzufügen
        $actIndex = 1; // Zähler für Akte
        
        // Debug-Ausgabe der benutzerdefinierten Akttitel
        if (isset($this->bookStructure['customActTitles'])) {
            file_put_contents('output/debug_log.txt', "Benutzerdefinierte Akttitel gefunden: " . 
                            print_r($this->bookStructure['customActTitles'], true), FILE_APPEND);
        } else {
            file_put_contents('output/debug_log.txt', "KEINE benutzerdefinierten Akttitel gefunden\n", FILE_APPEND);
        }
        
        // Überprüfen, ob 'acts' ein Array mit Kapitelstruktur oder ein Array mit Titeldaten ist
        $hasTraditionalStructure = false;
        if (isset($this->bookStructure['acts']) && is_array($this->bookStructure['acts'])) {
            // Prüfen, ob mindestens ein Act eine 'chapters' Array-Eigenschaft hat
            foreach ($this->bookStructure['acts'] as $actKey => $act) {
                if (is_array($act) && isset($act['chapters']) && is_array($act['chapters'])) {
                    $hasTraditionalStructure = true;
                    break;
                }
            }
        }
        
        if ($hasTraditionalStructure) {
            // Traditionelle Kapitelstruktur verwenden
            file_put_contents('output/debug_log.txt', "Verwende traditionelle Kapitelstruktur\n", FILE_APPEND);
            
            foreach ($this->bookStructure['acts'] as $actKey => $act) {
                // Akt-Titelseite einfügen
                $this->mpdf->AddPage();
                
                // Aktnummer und Titel bestimmen
                $actNumber = 0;
                preg_match('/(\d+)\./', $act['title'], $matches);
                if (isset($matches[1])) {
                    $actNumber = (int)$matches[1];
                } else {
                    $actNumber = $actIndex;
                }
                
                $actTitle = $act['title']; // Standard-Titel (z.B. "1. Akt")
                
                // Wenn benutzerdefinierte Aktnamen in den Metadaten existieren
                if (isset($this->bookStructure['customActTitles']) && is_array($this->bookStructure['customActTitles'])) {
                    // Suche nach einem passenden Akt in der Metadaten-Struktur
                    foreach ($this->bookStructure['customActTitles'] as $metaAct) {
                        if (isset($metaAct['number']) && $metaAct['number'] == $actNumber) {
                            $actTitle = $actNumber . ". Akt: " . $metaAct['title'];
                            file_put_contents('output/debug_log.txt', "Akttitel gefunden: $actTitle für Akt $actNumber\n", FILE_APPEND);
                            break;
                        }
                    }
                }
                
                // Lesezeichen für den Akt hinzufügen
                $this->mpdf->Bookmark($actTitle, 0);
                
                // HTML für Akt-Titel
                $actHtml = "<h1 class=\"act-title\">{$actTitle}</h1>";
                
                // Aktbild suchen
                $actImage = $this->findActImage($actNumber);
                
                if ($actImage) {
                    // Absoluten Pfad für das Bild erstellen
                    $absoluteImagePath = realpath($actImage);
                    
                    if ($absoluteImagePath) {
                        // Bild einfügen mit absolutem Pfad und expliziten Dimensionen
                        $actHtml .= "<div class=\"act-image\" style=\"text-align: center; margin: 2cm 0;\">";
                        // Prüfen, ob es sich um ein lokales Bild handelt
                        $imgInfo = getimagesize($absoluteImagePath);
                        $width = $imgInfo[0];
                        $height = $imgInfo[1];
                        
                        // Maximale Breite/Höhe berechnen
                        $maxWidth = 400; // px
                        $maxHeight = 500; // px
                        
                        // Skalierungsfaktor berechnen
                        $scaleWidth = $maxWidth / $width;
                        $scaleHeight = $maxHeight / $height;
                        $scale = min($scaleWidth, $scaleHeight, 1); // Nicht vergrößern, nur verkleinern
                        
                        // Neue Dimensionen berechnen
                        $newWidth = round($width * $scale);
                        $newHeight = round($height * $scale);
                        
                        // Bild mit expliziten Dimensionen einfügen
                        $actHtml .= "<img src=\"$absoluteImagePath\" width=\"$newWidth\" height=\"$newHeight\" />";
                        $actHtml .= "</div>";
                    }
                }
                
                $this->mpdf->WriteHTML($actHtml);
                
                // Kapitel verarbeiten
                if (isset($act['chapters']) && is_array($act['chapters'])) {
                    foreach ($act['chapters'] as $chapterTitle => $chapter) {
                        $this->mpdf->AddPage();
                        
                        // Lesezeichen für das Kapitel hinzufügen
                        $this->mpdf->Bookmark($chapter['title'], 1);
                        
                        // Kapitelüberschrift
                        $this->mpdf->WriteHTML("<h2 class=\"chapter-title\">{$chapter['title']}</h2>");
                        
                        // Kapitelinhalt als HTML (von Markdown konvertiert)
                        $html = $this->markdown->text($chapter['content']);
                        
                        // Typografische Verbesserungen
                        $html = $this->improveTypography($html);
                        
                        // Bilder im Markdown-Inhalt korrigieren
                        $html = $this->enhancedFixImagePaths($html);
                        
                        $this->mpdf->WriteHTML($html);
                    }
                }
                
                $actIndex++;
            }
        } else {
            // Alternative Verarbeitung, wenn keine traditionelle Struktur vorhanden ist
            file_put_contents('output/debug_log.txt', "Verwende alternative Struktur\n", FILE_APPEND);
            
            // Temporäre Variable für den Markdown-Inhalt
            $markdownContent = "";
            if (isset($this->bookStructure['_markdownContent'])) {
                $markdownContent = $this->bookStructure['_markdownContent'];
            } else {
                // Debug-Ausgabe des Markdown-Inhalts aus output/debug_markdown.md laden, falls vorhanden
                if (file_exists('output/debug_markdown.md')) {
                    $markdownContent = file_get_contents('output/debug_markdown.md');
                }
            }
            
            // Akte aus dem ursprünglichen Markdown-Inhalt extrahieren
            $lines = explode("\n", $markdownContent ?? '');
            $currentAct = null;
            $currentChapter = null;
            $chapterContent = '';
            
            foreach ($lines as $line) {
                // Akt-Überschrift (H2)
                if (preg_match('/^## (.+)$/', $line, $matches)) {
                    // Vorheriges Kapitel abschließen, falls vorhanden
                    if ($currentChapter !== null && !empty($chapterContent)) {
                        $this->mpdf->AddPage();
                        $this->mpdf->Bookmark($currentChapter, 1);
                        $this->mpdf->WriteHTML("<h2 class=\"chapter-title\">{$currentChapter}</h2>");
                        
                        $html = $this->markdown->text($chapterContent);
                        $html = $this->improveTypography($html);
                        $html = $this->enhancedFixImagePaths($html);
                        
                        $this->mpdf->WriteHTML($html);
                        $chapterContent = '';
                    }
                    
                    // Neuen Akt starten
                    $currentAct = trim($matches[1]);
                    $currentChapter = null;
                    
                    // Aktnummer extrahieren
                    $actNumber = 0;
                    preg_match('/(\d+)\./', $currentAct, $matches);
                    if (isset($matches[1])) {
                        $actNumber = (int)$matches[1];
                    } else {
                        $actNumber = $actIndex;
                    }
                    
                    // Aktname aus der JSON-Metadaten ergänzen, falls vorhanden
                    $actTitle = $currentAct;
                    if (isset($this->bookStructure['customActTitles']) && is_array($this->bookStructure['customActTitles'])) {
                        foreach ($this->bookStructure['customActTitles'] as $metaAct) {
                            if (isset($metaAct['number']) && $metaAct['number'] == $actNumber) {
                                $actTitle = $actNumber . ". Akt: " . $metaAct['title'];
                                file_put_contents('output/debug_log.txt', "Alt. Struktur: Akttitel gefunden: $actTitle für Akt $actNumber\n", FILE_APPEND);
                                break;
                            }
                        }
                    }
                    
                    // Akt-Seite hinzufügen
                    $this->mpdf->AddPage();
                    $this->mpdf->Bookmark($actTitle, 0);
                    
                    $actHtml = "<h1 class=\"act-title\">{$actTitle}</h1>";
                    
                    // Aktbild hinzufügen
                    $actImage = $this->findActImage($actNumber);
                    if ($actImage) {
                        $absoluteImagePath = realpath($actImage);
                        if ($absoluteImagePath) {
                            $actHtml .= "<div class=\"act-image\" style=\"text-align: center; margin: 2cm 0;\">";
                            $imgInfo = getimagesize($absoluteImagePath);
                            $width = $imgInfo[0];
                            $height = $imgInfo[1];
                            
                            $maxWidth = 400;
                            $maxHeight = 500;
                            
                            $scaleWidth = $maxWidth / $width;
                            $scaleHeight = $maxHeight / $height;
                            $scale = min($scaleWidth, $scaleHeight, 1);
                            
                            $newWidth = round($width * $scale);
                            $newHeight = round($height * $scale);
                            
                            $actHtml .= "<img src=\"$absoluteImagePath\" width=\"$newWidth\" height=\"$newHeight\" />";
                            $actHtml .= "</div>";
                        }
                    }
                    
                    $this->mpdf->WriteHTML($actHtml);
                    $actIndex++;
                    continue;
                }
                
                // Kapitel-Überschrift (H3)
                if (preg_match('/^### (.+)$/', $line, $matches)) {
                    // Vorheriges Kapitel abschließen, falls vorhanden
                    if ($currentChapter !== null && !empty($chapterContent)) {
                        $this->mpdf->AddPage();
                        $this->mpdf->Bookmark($currentChapter, 1);
                        $this->mpdf->WriteHTML("<h2 class=\"chapter-title\">{$currentChapter}</h2>");
                        
                        $html = $this->markdown->text($chapterContent);
                        $html = $this->improveTypography($html);
                        $html = $this->enhancedFixImagePaths($html);
                        
                        $this->mpdf->WriteHTML($html);
                        $chapterContent = '';
                    }
                    
                    // Neues Kapitel starten
                    $currentChapter = trim($matches[1]);
                    continue;
                }
                
                // Inhalt zum aktuellen Kapitel hinzufügen
                if ($currentChapter !== null) {
                    $chapterContent .= $line . "\n";
                }
            }
            
            // Letztes Kapitel abschließen
            if ($currentChapter !== null && !empty($chapterContent)) {
                $this->mpdf->AddPage();
                $this->mpdf->Bookmark($currentChapter, 1);
                $this->mpdf->WriteHTML("<h2 class=\"chapter-title\">{$currentChapter}</h2>");
                
                $html = $this->markdown->text($chapterContent);
                $html = $this->improveTypography($html);
                $html = $this->enhancedFixImagePaths($html);
                
                $this->mpdf->WriteHTML($html);
            }
        }
        
        // PDF speichern
        $this->mpdf->Output($outputPath, 'F');
        
        return true;
    }

    /**
     * Erweiterte Version für die Korrektur von Bildpfaden im HTML
     * 
     * @param string $html Das HTML mit Bildtags
     * @return string Korrigiertes HTML
     */
    private function enhancedFixImagePaths($html) {
        // Regulärer Ausdruck für HTML-Bilder: <img src="...">
        $pattern = '/<img\s+[^>]*?src=["\']([^"\']+)["\'][^>]*?>/i';
        
        $result = preg_replace_callback($pattern, function($matches) {
            $srcPath = $matches[1];
            $originalTag = $matches[0];
            
            // Debug-Ausgabe (Optional)
            file_put_contents('output/debug_log.txt', "Processing image: $srcPath\n", FILE_APPEND);
            
            // Liste von Orten, an denen das Bild gesucht werden soll
            $possibleLocations = [
                $srcPath, // Originalpfad
                realpath($srcPath), // Absoluter Pfad
                __DIR__ . '/' . $srcPath, // Relativ zum Skript
                __DIR__ . '/output/' . $srcPath, // Im output-Verzeichnis
                'output/' . $srcPath, // Alternative im output-Verzeichnis
            ];
            
            // Durch mögliche Pfade iterieren
            foreach ($possibleLocations as $path) {
                if ($path && file_exists($path)) {
                    // Prüfen, ob es sich um ein Bild handelt
                    if (exif_imagetype($path)) {
                        // Absoluten Pfad für das Bild erstellen
                        $absolutePath = realpath($path);
                        
                        // Debug-Ausgabe (Optional)
                        file_put_contents('output/debug_log.txt', "Found image at: $absolutePath\n", FILE_APPEND);
                        
                        // Bildgröße ermitteln
                        $imgInfo = getimagesize($absolutePath);
                        if ($imgInfo) {
                            $width = $imgInfo[0];
                            $height = $imgInfo[1];
                            
                            // Maximale Breite/Höhe für inline Bilder
                            $maxWidth = 350; // px
                            $maxHeight = 450; // px
                            
                            // Skalierungsfaktor berechnen
                            $scaleWidth = $maxWidth / $width;
                            $scaleHeight = $maxHeight / $height;
                            $scale = min($scaleWidth, $scaleHeight, 1); // Nicht vergrößern, nur verkleinern
                            
                            // Neue Dimensionen berechnen
                            $newWidth = round($width * $scale);
                            $newHeight = round($height * $scale);
                            
                            // Neues IMG-Tag mit absolutem Pfad und expliziten Dimensionen erstellen
                            $newTag = str_replace('src="' . $srcPath . '"', 
                                            'src="' . $absolutePath . '" ' .
                                            'width="' . $newWidth . '" ' .
                                            'height="' . $newHeight . '"', 
                                            $originalTag);
                            
                            return $newTag;
                        }
                        
                        // Wenn getimagesize fehlschlägt, nur den Pfad ersetzen
                        return str_replace('src="' . $srcPath . '"', 'src="' . $absolutePath . '"', $originalTag);
                    }
                }
            }
            
            // Debug-Ausgabe (Optional)
            file_put_contents('output/debug_log.txt', "Could not find image: $srcPath\n", FILE_APPEND);
            
            // Wenn das Bild nicht gefunden wurde, das Original-Tag zurückgeben
            return $originalTag;
        }, $html);
        
        return $result;
    }

    /**
     * Sucht nach einem Aktbild in verschiedenen möglichen Pfaden
     * 
     * @param int $actIndex Nummer des Akts (1, 2, 3, ...)
     * @return string|false Pfad zum Bild oder false, wenn keines gefunden wurde
     */
    private function findActImage($actIndex) {
        // Debug-Ausgabe
        file_put_contents('output/debug_log.txt', "Detaillierte Bildsuche für Akt $actIndex\n", FILE_APPEND);
        
        // Mögliche Bildpfade spezifisch für Ihre Struktur
        $possiblePaths = [
            // Chroniken-der-Konvergenz Spezifische Pfade
            __DIR__ . "/Akt$actIndex.png",
            __DIR__ . "/{$actIndex}. Akt/Akt$actIndex.png",
            __DIR__ . "/1 - Schattenprojekt/Akt$actIndex.png",
            __DIR__ . "/1 - Schattenprojekt/{$actIndex}. Akt/Akt$actIndex.png",
            __DIR__ . "/Chroniken-der-Konvergenz/1 - Schattenprojekt/Akt$actIndex.png",
            __DIR__ . "/Chroniken-der-Konvergenz/1 - Schattenprojekt/{$actIndex}. Akt/Akt$actIndex.png",
            
            // Standard-Pfade
            __DIR__ . "/output/Akt$actIndex.png",
            __DIR__ . "/images/Akt$actIndex.png",
            
            // Varianten mit Kleinbuchstaben
            __DIR__ . "/akt$actIndex.png",
            __DIR__ . "/{$actIndex}. akt/akt$actIndex.png",
            
            // Varianten ohne "Akt" Präfix
            __DIR__ . "/{$actIndex}.png",
            __DIR__ . "/{$actIndex}. Akt/{$actIndex}.png",
            
            // JPG und JPEG Varianten
            __DIR__ . "/Akt$actIndex.jpg",
            __DIR__ . "/{$actIndex}. Akt/Akt$actIndex.jpg",
            __DIR__ . "/Akt$actIndex.jpeg",
            __DIR__ . "/{$actIndex}. Akt/Akt$actIndex.jpeg"
        ];
        
        // Alle möglichen Pfade protokollieren
        foreach ($possiblePaths as $path) {
            file_put_contents('output/debug_log.txt', "Prüfe Pfad: $path\n", FILE_APPEND);
            if (file_exists($path)) {
                file_put_contents('output/debug_log.txt', "Datei existiert! Prüfe Bildtyp...\n", FILE_APPEND);
                // Sicherstellen, dass es sich um ein Bild handelt
                if (@getimagesize($path)) {
                    file_put_contents('output/debug_log.txt', "ERFOLG: Bild gefunden: $path\n", FILE_APPEND);
                    return $path;
                } else {
                    file_put_contents('output/debug_log.txt', "Datei existiert, ist aber kein Bild: $path\n", FILE_APPEND);
                }
            }
        }
        
        file_put_contents('output/debug_log.txt', "FEHLER: Kein Bild für Akt $actIndex gefunden\n", FILE_APPEND);
        return false;
    }

   
    /**
     * Erstellt die Titelseite des Buches
     */
    private function createTitlePage() {
        $title = $this->bookStructure['title'];
        $author = $this->bookStructure['author'] ?? '';
        $coverImage = $this->bookStructure['cover_image'] ?? '';
        
        // Cover-Bild, falls vorhanden
        if (!empty($coverImage) && file_exists($coverImage)) {
            // Eine separate Seite für das Cover-Bild hinzufügen
            $this->mpdf->AddPage();
            $html = "<div style=\"text-align: center; height: 100%; page-break-after: always;\">";
            $html .= "<img src=\"$coverImage\" style=\"max-width: 100%; max-height: 100%;\" />";
            $html .= "</div>";
            $this->mpdf->WriteHTML($html);
        }
        
        // Titelseite mit Buch-Titel und Autor
        $this->mpdf->AddPage();
        $html = <<<HTML
        <div style="text-align: center; margin-top: 40%; page-break-after: always;">
            <h1 style="font-size: 24pt; margin-bottom: 2cm;">{$title}</h1>
            <h2 style="font-size: 14pt;">{$author}</h2>
        </div>
        HTML;
        
        $this->mpdf->WriteHTML($html);
    }
    
    /**
     * Verbessert die Typografie des HTML-Inhalts
     * 
     * @param string $html HTML-Inhalt
     * @return string Verbesserter HTML-Inhalt
     */
    private function improveTypography($html) {
        // Anführungszeichen verbessern
        $html = preg_replace('/"([^"]*)"/', '„$1"', $html);
        
        // Gedankenstriche richtig setzen
        $html = preg_replace('/(\s)-(\s)/', '$1–$2', $html);
        
        // Apostrophe verbessern
        $html = str_replace("'", '´', $html);
        
        return $html;
    }
}

/**
 * Web-Interface zum Hochladen und Verarbeiten von Markdown-Dateien
 */
class BookGeneratorWeb {
    private $generator;
    
    public function __construct() {
        $this->generator = new BookGenerator();
    }
    
    /**
     * Verarbeitet ein hochgeladenes Markdown-File
     */
    public function handleFileUpload() {
        if (!isset($_FILES['markdown_file']) || $_FILES['markdown_file']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Fehler beim Hochladen der Datei'];
        }
        
        $tempFile = $_FILES['markdown_file']['tmp_name'];
        $outputFile = 'output/book_' . time() . '.pdf';
        
        try {
            $this->generator->loadFromFile($tempFile);
            $this->generator->generatePdf($outputFile);
            return [
                'success' => true, 
                'message' => 'PDF erfolgreich erstellt', 
                'file' => $outputFile
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Lädt Markdown-Dateien aus einer Verzeichnisstruktur und kombiniert sie
     * 
     * @param string $dirPath Pfad zum Buchverzeichnis
     * @return bool Erfolg
     */
    public function loadFromDirectory($dirPath) {
        if (!is_dir($dirPath)) {
            throw new Exception("Verzeichnis nicht gefunden: $dirPath");
        }
        
        // Sammeln von Buchmetadaten (optional aus einer metadata.json oder ähnlichem)
        $bookTitle = basename($dirPath); // Verwende Verzeichnisnamen als Buchtitel
        $author = ""; // Optional: Aus metadata.json laden
        
        // Vorbereiten der Buchstruktur
        $markdownContent = "# $bookTitle\n";
        if (!empty($author)) {
            $markdownContent .= "**Autor:** $author\n\n";
        }
        
        // Akt-Verzeichnisse finden und sortieren
        $actDirs = glob($dirPath . "/*. Akt", GLOB_ONLYDIR);
        // Natürliche Sortierung nach Aktnummer
        usort($actDirs, function($a, $b) {
            preg_match('/(\d+)\. Akt$/', $a, $matchesA);
            preg_match('/(\d+)\. Akt$/', $b, $matchesB);
            
            $numA = isset($matchesA[1]) ? intval($matchesA[1]) : 0;
            $numB = isset($matchesB[1]) ? intval($matchesB[1]) : 0;
            
            return $numA - $numB;
        });
        
        // Jeden Akt verarbeiten
        foreach ($actDirs as $actDir) {
            $actName = basename($actDir);
            $markdownContent .= "\n## $actName\n";
            
            // Kapitel-Dateien finden und sortieren
            $chapterFiles = glob($actDir . "/*.md");
            // Natürliche Sortierung nach Kapitelnummer
            usort($chapterFiles, function($a, $b) {
                preg_match('/(\d+)\. Kapitel/', $a, $matchesA);
                preg_match('/(\d+)\. Kapitel/', $b, $matchesB);
                
                $numA = isset($matchesA[1]) ? intval($matchesA[1]) : 0;
                $numB = isset($matchesB[1]) ? intval($matchesB[1]) : 0;
                
                return $numA - $numB;
            });
            
            // Jedes Kapitel verarbeiten
            foreach ($chapterFiles as $chapterFile) {
                $chapterName = basename($chapterFile, '.md');
                $markdownContent .= "\n### $chapterName\n";
                
                // Kapitelinhalt laden
                $chapterContent = file_get_contents($chapterFile);
                $markdownContent .= $chapterContent . "\n";
            }
        }
        
        // Mit der generierten Markdown-Datei fortfahren
        return $this->loadFromString($markdownContent);
    }

    /**
     * Verarbeitet GitHub-Repository-Verzeichnis
     */
    public function handleGitHubDir() {
        if (!isset($_POST['github_repo']) || empty($_POST['github_repo'])) {
            return ['success' => false, 'message' => 'GitHub-Repository nicht angegeben'];
        }
        
        $repo = $_POST['github_repo'];
        $dir = $_POST['github_dir'] ?? '';
        $token = $_POST['github_token'] ?? null;
        $outputFile = 'output/book_' . time() . '.pdf';
        
        // GitHub-URL parsen (Format: https://github.com/username/repo)
        if (preg_match('#https?://github\.com/([^/]+)/([^/]+)(?:/tree/([^/]+)(?:/(.+))?)?#', $repo, $matches)) {
            $username = $matches[1];
            $repository = $matches[2];
            $branch = $matches[3] ?? 'main';
            $path = $matches[4] ?? $dir;
            
            try {
                $this->generator->loadFromGitHubDirectory($username, $repository, $path, $branch, $token);
                $this->generator->generatePdf($outputFile);
                return [
                    'success' => true, 
                    'message' => 'PDF erfolgreich erstellt', 
                    'file' => $outputFile
                ];
            } catch (Exception $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }
        
        return ['success' => false, 'message' => 'Ungültige GitHub-URL'];
    }
    
    /**
     * Verarbeitet GitHub-Repository-URL
     */
    public function handleGitHubRepo() {
        if (!isset($_POST['github_repo']) || empty($_POST['github_repo'])) {
            return ['success' => false, 'message' => 'GitHub-Repository nicht angegeben'];
        }
        
        $repo = $_POST['github_repo'];
        $path = $_POST['github_path'] ?? '';
        $token = $_POST['github_token'] ?? null;
        $outputFile = 'output/book_' . time() . '.pdf';
        
        // GitHub-URL parsen (Format: https://github.com/username/repo)
        if (preg_match('#https?://github\.com/([^/]+)/([^/]+)(?:/tree/([^/]+)(?:/(.+))?)?#', $repo, $matches)) {
            $username = $matches[1];
            $repository = $matches[2];
            $branch = $matches[3] ?? 'main';
            $filePath = $matches[4] ?? $path;
            
            try {
                $this->generator->loadFromGitHub($username, $repository, $filePath, $branch, $token);
                $this->generator->generatePdf($outputFile);
                return [
                    'success' => true, 
                    'message' => 'PDF erfolgreich erstellt', 
                    'file' => $outputFile
                ];
            } catch (Exception $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }
        
        return ['success' => false, 'message' => 'Ungültige GitHub-URL'];
    }
    
    /**
     * Rendert das Eingabeformular
     */
    public function renderForm() {
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <title>Markdown zu PDF Buchgenerator</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    line-height: 1.6;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .form-group { margin-bottom: 20px; }
                label { display: block; margin-bottom: 5px; font-weight: bold; }
                input, textarea, select { width: 100%; padding: 8px; }
                button { padding: 10px 15px; background: #4CAF50; color: white; border: none; cursor: pointer; }
                .tabs { display: flex; margin-bottom: 20px; }
                .tab { padding: 10px 15px; cursor: pointer; border: 1px solid #ddd; }
                .tab.active { background: #f0f0f0; }
                .tab-content { display: none; }
                .tab-content.active { display: block; }
                .mode-option { margin-top: 15px; padding: 10px; border: 1px solid #eee; }
                .mode-option.hidden { display: none; }
            </style>
        </head>
        <body>
            <h1>Markdown zu PDF Buchgenerator</h1>
            
            <div class="tabs">
                <div class="tab active" data-tab="file-upload">Datei hochladen</div>
                <div class="tab" data-tab="github">GitHub Repository</div>
            </div>
            
            <div id="file-upload" class="tab-content active">
                <form action="" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="markdown_file">Markdown-Datei auswählen:</label>
                        <input type="file" name="markdown_file" id="markdown_file" accept=".md" required>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="upload_file">PDF generieren</button>
                    </div>
                </form>
            </div>
            
            <div id="github" class="tab-content">
                <form action="" method="post">
                    <div class="form-group">
                        <label for="github_repo">GitHub Repository URL:</label>
                        <input type="url" name="github_repo" id="github_repo" 
                            placeholder="https://github.com/username/repository" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="github_token">GitHub Token (für private Repositories):</label>
                        <input type="password" name="github_token" id="github_token">
                    </div>
                    
                    <div class="form-group">
                        <label for="github_mode">Verarbeitungsmodus:</label>
                        <select name="github_mode" id="github_mode" onchange="toggleGitHubMode()">
                            <option value="file">Einzelne Markdown-Datei</option>
                            <option value="directory">Verzeichnisstruktur (Akte und Kapitel)</option>
                        </select>
                    </div>
                    
                    <div id="github-file-mode" class="mode-option">
                        <div class="form-group">
                            <label for="github_path">Pfad zur Markdown-Datei:</label>
                            <input type="text" name="github_path" id="github_path" 
                                placeholder="z.B. docs/book.md">
                        </div>
                    </div>
                    
                    <div id="github-dir-mode" class="mode-option hidden">
                        <div class="form-group">
                            <label for="github_dir">Pfad zum Buchverzeichnis:</label>
                            <input type="text" name="github_dir" id="github_dir" 
                                placeholder="z.B. MeinBuch">
                            <small>Verzeichnis mit Akt-Unterordnern (Format: "1. Akt", "2. Akt", usw.)</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="github_submit">PDF generieren</button>
                    </div>
                </form>
            </div>
            
            <script>
                // Tab-Funktionalität
                document.querySelectorAll('.tab').forEach(tab => {
                    tab.addEventListener('click', () => {
                        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                        
                        tab.classList.add('active');
                        document.getElementById(tab.dataset.tab).classList.add('active');
                    });
                });
                
                // GitHub-Modus umschalten
                function toggleGitHubMode() {
                    const mode = document.getElementById('github_mode').value;
                    
                    if (mode === 'file') {
                        document.getElementById('github-file-mode').classList.remove('hidden');
                        document.getElementById('github-dir-mode').classList.add('hidden');
                    } else {
                        document.getElementById('github-file-mode').classList.add('hidden');
                        document.getElementById('github-dir-mode').classList.remove('hidden');
                    }
                }
            </script>
        </body>
        </html>
        <?php
    }
}

if (isset($_POST['upload_file'])) {
    $webInterface = new BookGeneratorWeb();
    $result = $webInterface->handleFileUpload();
    // Ergebnis anzeigen
    echo '<div class="result">' . $result['message'] . '</div>';
    if ($result['success']) {
        echo '<a href="' . $result['file'] . '">PDF herunterladen</a>';
    }
} else if (isset($_POST['github_submit'])) {
    $webInterface = new BookGeneratorWeb();
    
    // Prüfen, welcher GitHub-Modus gewählt wurde
    if ($_POST['github_mode'] === 'directory') {
        $result = $webInterface->handleGitHubDir();
    } else {
        $result = $webInterface->handleGitHubRepo();
    }
    
    // Ergebnis anzeigen
    echo '<div class="result">' . $result['message'] . '</div>';
    if ($result['success']) {
        echo '<a href="' . $result['file'] . '">PDF herunterladen</a>';
    }
} else {
    // Formular anzeigen
    $webInterface = new BookGeneratorWeb();
    $webInterface->renderForm();
}
