/**
 * Render a date string as yyyy-MM-dd, stripping any time/timezone suffix.
 */
export function DateView({ value }: { value: string | null | undefined }) {
    if (!value) return <span className="text-muted-foreground">&mdash;</span>;
    return <>{value.split('T')[0]}</>;
}
