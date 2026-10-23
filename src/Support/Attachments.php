<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\Mime\MimeTypes;

/**
 * Ce qu'on accepte comme pièce jointe d'un ticket, d'une tâche ou d'un message, contrôlé sur le fichier lui-même, par
 * quelque chemin qu'il arrive (un formulaire, l'historique, l'autre application, `tma:*`) :
 *
 * - son type réel, détecté sur le contenu, est dans la liste blanche `filament-tma.attachments.accepted_types` ;
 * - son extension va avec ce type : un `.html` qui contient du texte, un `.pdf` qui n'en est pas un, sont refusés ;
 * - un fichier Markdown (`.md`, `.markdown`) est du texte, même quand il contient des balises (le contenu se détecte
 *   alors en HTML) : il s'enregistre en `text/markdown` (`text/markdown` doit être dans la liste) ;
 * - un document Office (docx, xlsx, pptx) ou OpenDocument ne contient ni macro, ni contrôle ActiveX, ni objet OLE
 *   incorporé, ni modèle ou objet lié à distance (un `.xlsm` renommé en `.xlsx`, par exemple, est refusé) ; il ne
 *   dépasse pas `attachments.max_unpacked_mb` une fois décompressé. Un document chiffré, qu'on ne peut pas ouvrir, n'a
 *   pas l'un des types acceptés.
 *
 * Les fichiers se servent toujours en téléchargement, sans être interprétés (`Http\Controllers\MediaController`) ;
 * seules les images s'affichent.
 */
final class Attachments
{
    public const MARKDOWN = 'text/markdown';

    /** Les documents Office (OOXML) et la partie principale que déclare un vrai document de chaque sorte. */
    private const OFFICE = [
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml'],
    ];

    private const OPEN_DOCUMENT = [
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
    ];

    /** Les extensions d'un type, quand celles de Symfony ne suffisent pas ; les autres types prennent les siennes. */
    private const EXTENSIONS = [
        'text/plain' => ['txt', 'text', 'log', 'csv'],
        'text/csv' => ['csv', 'txt'],
        self::MARKDOWN => ['md', 'markdown'],
        'image/jpeg' => ['jpg', 'jpeg'],
    ];

    /** Dans une archive Office ou OpenDocument : macros, contrôles ActiveX, objets OLE et documents à macros incorporés. */
    private const ACTIVE_CONTENT = '~(^|/)vbaproject\.bin$|(^|/)vbadata\.xml$|(^|/)activex/|/embeddings/[^/]*\.(bin|xlsm|xlsb|docm|pptm|ppsm|exe)$|^(basic|scripts)/~';

    /** Des relations vers l'extérieur qui chargeraient du contenu actif à l'ouverture : un modèle, un objet OLE, un sous-document. */
    private const EXTERNAL_ACTIVE = '~<Relationship\b(?=[^>]*TargetMode="External")(?=[^>]*Type="[^"]*/(attachedTemplate|oleObject|subDocument|frame)")[^>]*>~i';

    private const MAX_ENTRIES = 10000;

    /** @return array<int, string> la liste blanche */
    public static function acceptedTypes(): array
    {
        return array_values((array) config('filament-tma.attachments.accepted_types', []));
    }

    /**
     * Le type sous lequel le fichier s'enregistre : celui détecté sur son contenu, sauf un Markdown (`text/markdown`,
     * quel que soit le texte détecté) et un document Office que la détection ne voit que comme une archive zip.
     */
    public static function typeOf(string $detected, string $fileName): string
    {
        $extension = self::extension($fileName);

        return match (true) {
            in_array($extension, self::EXTENSIONS[self::MARKDOWN], true) && str_starts_with($detected, 'text/') => self::MARKDOWN,
            $detected === 'application/zip' && isset(self::OFFICE[$extension]) => self::OFFICE[$extension][0],
            default => $detected,
        };
    }

    /**
     * Un type de la liste blanche, et une extension qui va avec (sans regarder dans le fichier : voir `problem()`). Une
     * image peut porter l'extension d'une autre image acceptée (une photo JPEG nommée `.png`) : elle s'affiche sous son
     * type réel.
     */
    public static function accepts(string $detected, string $fileName): bool
    {
        $type = self::typeOf($detected, $fileName);
        $extensions = str_starts_with($type, 'image/')
            ? collect(self::acceptedTypes())->filter(fn (string $accepted): bool => str_starts_with($accepted, 'image/'))->flatMap(fn (string $image): array => self::extensionsOf($image))->all()
            : self::extensionsOf($type);

        return in_array($type, self::acceptedTypes(), true) && in_array(self::extension($fileName), $extensions, true);
    }

    /** Ce qui ne va pas dans un fichier (sur un chemin local), ou null s'il est accepté. */
    public static function problem(string $path, string $fileName): ?string
    {
        $detected = (string) MimeTypes::getDefault()->guessMimeType($path);

        if (! self::accepts($detected, $fileName)) {
            return __('Ce type de fichier n’est pas accepté : :name.', ['name' => $fileName]);
        }

        $type = self::typeOf($detected, $fileName);

        return match (true) {
            $type === self::MARKDOWN => str_contains((string) file_get_contents($path), "\0") ? __('Ce fichier Markdown n’est pas du texte : :name.', ['name' => $fileName]) : null,
            in_array($type, array_column(self::OFFICE, 0), true), in_array($type, self::OPEN_DOCUMENT, true) => self::documentProblem($path, $type, $fileName),
            default => null,
        };
    }

    /** Pareil, pour un fichier sur un disque (celui des pièces jointes) : lu sur place, ou copié le temps du contrôle. */
    public static function problemOnDisk(string $path, string $disk, string $fileName): ?string
    {
        $storage = Storage::disk($disk);
        $local = rescue(fn (): string => $storage->path($path), null, false);

        if ($local !== null && is_file($local)) {
            return self::problem($local, $fileName);
        }

        return self::withTemporaryFile((string) $storage->get($path), fn (string $file): ?string => self::problem($file, $fileName));
    }

    /** Pareil, pour un contenu reçu (l'autre application). */
    public static function problemInContent(string $content, string $fileName): ?string
    {
        return self::withTemporaryFile($content, fn (string $file): ?string => self::problem($file, $fileName));
    }

    /** Refuse le fichier s'il ne va pas. */
    public static function assertAcceptable(?string $problem, string $field = 'attachments'): void
    {
        if ($problem !== null) {
            throw ValidationException::withMessages([$field => $problem]);
        }
    }

    /**
     * Un champ de pièces jointes : ce que le navigateur propose (les types de la liste, reconnus aussi par leur
     * extension quand le navigateur ne connaît pas le type, un `.md` sous Windows par exemple), et le contrôle de
     * chaque fichier par le serveur (`rule()`), à la place de la règle `mimetypes` de Filament (qui refuserait un
     * Markdown dont le texte se détecte en HTML). Un fichier rangé directement par medialibrary garde son nom
     * d'origine (simplifié), avec l'extension contrôlée.
     *
     * @template T of BaseFileUpload
     *
     * @param  T  $field
     * @return T
     */
    public static function field(BaseFileUpload $field): BaseFileUpload
    {
        $types = self::acceptedTypes();
        (function () use ($types): void {
            $this->acceptedFileTypes = $types;
        })->call($field);

        $field->mimeTypeMap(self::extensionMap())->rule(self::rule());

        if ($field instanceof SpatieMediaLibraryFileUpload) {
            $field->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => Uploads::safeFileName($file->getClientOriginalName()));
        }

        return $field;
    }

    /** Le contrôle de chaque fichier envoyé par un formulaire (`problem()`). */
    public static function rule(): ValidationRule
    {
        return new class implements ValidationRule
        {
            public function validate(string $attribute, mixed $value, \Closure $fail): void
            {
                if (! $value instanceof \Illuminate\Http\UploadedFile) {
                    return;
                }

                $problem = is_file((string) $value->getRealPath())
                    ? Attachments::problem((string) $value->getRealPath(), $value->getClientOriginalName())
                    : Attachments::problemInContent((string) $value->get(), $value->getClientOriginalName());

                if ($problem !== null) {
                    $fail($problem);
                }
            }
        };
    }

    /** Enregistré par medialibrary : le type de `typeOf()` (un Markdown reste `text/markdown`), pour les fichiers du module. */
    public static function normalize(Media $media): void
    {
        $owners = [(new Entry)->getMorphClass(), (new Ticket)->getMorphClass(), (new ProjectTask)->getMorphClass()];

        if ($media->collection_name === Entry::ATTACHMENTS_COLLECTION && in_array($media->model_type, $owners, true)) {
            $media->mime_type = self::typeOf((string) $media->mime_type, (string) $media->file_name);
        }
    }

    /** @return array<string, string> extension => type, pour reconnaître un fichier par son extension dans le navigateur */
    public static function extensionMap(): array
    {
        $map = [];

        foreach (self::acceptedTypes() as $type) {
            foreach (self::extensionsOf($type) as $extension) {
                $map[$extension] ??= $type;
            }
        }

        return $map;
    }

    /** @return array<int, string> */
    private static function extensionsOf(string $type): array
    {
        return self::EXTENSIONS[$type] ?? MimeTypes::getDefault()->getExtensions($type);
    }

    private static function extension(string $fileName): string
    {
        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    }

    /**
     * Un document Office ou OpenDocument : une archive lisible, pas trop grosse une fois décompressée, sans contenu
     * actif ; pour Office, un vrai document de la sorte annoncée, sans type « à macros » ni relation active externe.
     */
    private static function documentProblem(string $path, string $type, string $fileName): ?string
    {
        $zip = new \ZipArchive;

        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            return __('Ce document est illisible : :name.', ['name' => $fileName]);
        }

        try {
            if ($zip->numFiles > self::MAX_ENTRIES) {
                return __('Ce document est trop gros une fois décompressé : :name.', ['name' => $fileName]);
            }

            $unpacked = 0;
            $maxUnpacked = (int) config('filament-tma.attachments.max_unpacked_mb', 250) * 1024 * 1024;
            $active = __('Ce document contient des macros ou du contenu actif, refusé pour la sécurité : :name. Enregistrez-le sans macro (.docx, .xlsx, .pptx).', ['name' => $fileName]);

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $unpacked += (int) $entry['size'];

                if ($unpacked > $maxUnpacked) {
                    return __('Ce document est trop gros une fois décompressé : :name.', ['name' => $fileName]);
                }

                if (preg_match(self::ACTIVE_CONTENT, strtolower((string) $entry['name']))) {
                    return $active;
                }

                if (str_ends_with(strtolower((string) $entry['name']), '.rels') && preg_match(self::EXTERNAL_ACTIVE, (string) $zip->getFromIndex($i))) {
                    return $active;
                }
            }

            if (in_array($type, self::OPEN_DOCUMENT, true)) {
                return null;
            }

            $contentTypes = (string) $zip->getFromName('[Content_Types].xml');
            $main = collect(self::OFFICE)->firstWhere(0, $type)[1];

            if (stripos($contentTypes, 'macroEnabled') !== false || stripos($contentTypes, 'vbaProject') !== false) {
                return $active;
            }

            return str_contains($contentTypes, $main) ? null : __('Ce document n’est pas ce que dit son extension : :name.', ['name' => $fileName]);
        } finally {
            $zip->close();
        }
    }

    /** @param  \Closure(string): ?string  $check */
    private static function withTemporaryFile(string $content, \Closure $check): ?string
    {
        $file = tempnam(sys_get_temp_dir(), 'tma');

        try {
            file_put_contents($file, $content);

            return $check($file);
        } finally {
            @unlink($file);
        }
    }
}
