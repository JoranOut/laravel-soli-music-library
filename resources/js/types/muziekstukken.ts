export type Orchestra = {
    id: number;
    name: string;
    abbreviation: string;
    type: string;
    is_active: boolean;
    sort_order: number;
};

export type InstrumentFamily = {
    id: number;
    name: string;
};

export type InstrumentType = {
    id: number;
    name: string;
    instrument_family_id: number;
    instrument_family: InstrumentFamily;
    sort_order: number;
    aliases: string[];
};

export type Part = {
    id: number;
    piece_id: number;
    instrument_type_id: number;
    is_conductor: boolean;
    voice: number | null;
    amount_bought: number | null;
    original_filename: string;
    instrument_type: InstrumentType;
    download_url?: string | null;
};

export type OrchestraUsage = {
    id: number;
    piece_id: number;
    orchestra_id: number;
    orchestra: Orchestra;
    van: string | null;
    tot: string | null;
    details: string | null;
};

export type Piece = {
    id: number;
    title: string;
    composer: string | null;
    arranger: string | null;
    publisher: string | null;
    difficulty: string | null;
    notes: string | null;
    bought_for: string | null;
    buy_date: string | null;
    genre: string[] | null;
    music_type: string | null;
    archive_number: string | null;
    audio_youtube_url: string | null;
    audio_file_path: string | null;
    deleted_at: string | null;
    orchestras: Orchestra[];
    orchestra_usages: OrchestraUsage[];
    parts: Part[];
    parts_count?: number;
};

export type OrchestraGroup = {
    orchestra: Orchestra;
    instruments: InstrumentType[];
    pieces: DashboardPiece[];
};

export type DashboardPiece = {
    id: number;
    title: string;
    composer: string | null;
    audio_youtube_url: string | null;
    audio_url: string | null;
    parts: Part[];
};

export type PaginatedData<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
};
