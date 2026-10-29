export type AttachmentKind = 'image' | 'file';

export type AttachmentConfig = {
    image_extensions: string[];
    file_extensions: string[];
    video_extensions: string[];
    audio_extensions: string[];
    accept: string;
    default_max_kilobytes: number;
    large_max_kilobytes: number;
};

export type Attachment = {
    id: string;
    url: string;
    name: string;
    kind: AttachmentKind;
    mime: string;
    size: number;
};
