<?php

namespace App\Enums;

enum AttachmentKind: string
{
    case Image = 'image';
    case File = 'file';

    public const DEFAULT_MAX_KILOBYTES = 10240;

    public const LARGE_MAX_KILOBYTES = 102400;

    /**
     * Video, audio and archive extensions, which get the larger size cap.
     *
     * @var list<string>
     */
    private const LARGE_EXTENSIONS = [
        'mp4', 'mov', 'm4v', 'webm', 'mkv', 'avi',
        'mp3', 'wav', 'm4a', 'aac', 'ogg', 'oga', 'opus', 'flac',
        '7z', 'tar', 'tgz', 'gz', 'bz2', 'xz',
    ];

    /**
     * Extensions accepted for upload. Active content (html, svg, js) and executables are excluded.
     *
     * @return list<string>
     */
    public function extensions(): array
    {
        return match ($this) {
            self::Image => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            self::File => [
                'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                'odt', 'ods', 'odp', 'rtf', 'txt', 'md', 'markdown',
                'csv', 'json', 'zip',
                ...self::LARGE_EXTENSIONS,
            ],
        };
    }

    public function filterLabel(): string
    {
        return match ($this) {
            self::Image => 'Images',
            self::File => 'Documents',
        };
    }

    public static function maxKilobytesFor(string $extension): int
    {
        return in_array(strtolower($extension), self::LARGE_EXTENSIONS, true)
            ? self::LARGE_MAX_KILOBYTES
            : self::DEFAULT_MAX_KILOBYTES;
    }

    /**
     * @return list<string>
     */
    public static function acceptedExtensions(): array
    {
        return array_merge(...array_map(
            fn (self $kind) => $kind->extensions(),
            self::cases(),
        ));
    }

    /**
     * Upload constraints shared with the client.
     *
     * @return array{image_extensions: list<string>, file_extensions: list<string>, video_extensions: list<string>, audio_extensions: list<string>, accept: string, default_max_kilobytes: int, large_max_kilobytes: int}
     */
    public static function config(): array
    {
        $extensions = self::acceptedExtensions();

        return [
            'image_extensions' => self::Image->extensions(),
            'file_extensions' => self::File->extensions(),
            'video_extensions' => ['mp4', 'm4v', 'mov', 'webm'],
            'audio_extensions' => ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'oga', 'opus', 'flac'],
            'accept' => implode(',', array_map(fn (string $extension) => '.'.$extension, $extensions)),
            'default_max_kilobytes' => self::DEFAULT_MAX_KILOBYTES,
            'large_max_kilobytes' => self::LARGE_MAX_KILOBYTES,
        ];
    }

    public static function fromMimeType(string $mimeType): self
    {
        return str_starts_with($mimeType, 'image/') ? self::Image : self::File;
    }
}
