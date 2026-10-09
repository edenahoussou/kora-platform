export type HealthCheckStatus =
    'ok' | 'warning' | 'failed' | 'crashed' | 'skipped';

export type HealthCheckItem = {
    name: string;
    label: string;
    status: HealthCheckStatus;
    summary: string;
    message: string;
    meta: Record<string, unknown>;
    endedAt: string | null;
};

export type LogFileItem = {
    identifier: string;
    name: string;
    path: string;
    size_formatted: string;
    earliest_timestamp: string | null;
    latest_timestamp: string | null;
};

export type LogLevelCount = {
    level: string;
    level_name: string;
    level_class: string;
    count: number;
    selected: boolean;
};

export type LogEntry = {
    index: number;
    file_identifier: string;
    level: string;
    level_name: string;
    level_class: string;
    datetime: string | null;
    time: string | null;
    message: string;
    context: unknown;
    extra: unknown;
    full_text?: string;
};

export type LogsPagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

export type LogsResponse = {
    logs: LogEntry[];
    levelCounts: LogLevelCount[];
    pagination: LogsPagination | null;
};

export const LOG_LEVELS = [
    'emergency',
    'alert',
    'critical',
    'error',
    'warning',
    'notice',
    'info',
    'debug',
] as const;

export type LogLevel = (typeof LOG_LEVELS)[number];
