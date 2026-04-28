import * as React from 'react';
import { useRef, useState } from 'react';
import * as Popover from '@radix-ui/react-popover';
import { Check, ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';

type ComboboxOption = {
    value: string;
    label: string;
    group?: string;
};

type ComboboxProps = {
    options: ComboboxOption[];
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    className?: string;
    allowCustom?: boolean;
};

function Combobox({ options, value, onChange, placeholder, className, allowCustom = false }: ComboboxProps) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);

    const selectedLabel = options.find((o) => o.value === value)?.label ?? value;

    const filtered = search
        ? options.filter((o) => o.label.toLowerCase().includes(search.toLowerCase()))
        : options;

    // Group options
    const grouped = new Map<string, ComboboxOption[]>();
    for (const opt of filtered) {
        const group = opt.group ?? '';
        if (!grouped.has(group)) grouped.set(group, []);
        grouped.get(group)!.push(opt);
    }

    function handleSelect(optionValue: string) {
        onChange(optionValue);
        setSearch('');
        setOpen(false);
    }

    function handleInputChange(e: React.ChangeEvent<HTMLInputElement>) {
        setSearch(e.target.value);
        if (!open) setOpen(true);
        if (allowCustom) {
            onChange(e.target.value);
        }
    }

    function handleKeyDown(e: React.KeyboardEvent) {
        if (e.key === 'Escape') {
            setOpen(false);
            setSearch('');
        }
    }

    return (
        <Popover.Root open={open} onOpenChange={setOpen}>
            <Popover.Trigger asChild>
                <div
                    className={cn(
                        'relative flex h-9 w-full items-center rounded-md border border-input bg-transparent text-sm shadow-xs transition-colors focus-within:ring-1 focus-within:ring-ring',
                        className,
                    )}
                >
                    {!search && selectedLabel && (
                        <span className={cn(
                            'pointer-events-none absolute inset-0 flex items-center px-3',
                            open ? 'text-muted-foreground' : 'text-foreground',
                        )}>
                            {selectedLabel}
                        </span>
                    )}
                    <input
                        ref={inputRef}
                        className="relative h-full w-full bg-transparent px-3 py-1 outline-none placeholder:text-muted-foreground"
                        value={search}
                        onChange={handleInputChange}
                        onFocus={() => {
                            setOpen(true);
                            setSearch('');
                        }}
                        onBlur={() => setSearch('')}
                        onKeyDown={handleKeyDown}
                        placeholder={!selectedLabel ? placeholder : undefined}
                    />
                    <ChevronDown className="mr-2 size-4 shrink-0 opacity-50" />
                </div>
            </Popover.Trigger>
            <Popover.Portal>
                <Popover.Content
                    className="z-50 max-h-[300px] w-[var(--radix-popover-trigger-width)] overflow-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md"
                    sideOffset={4}
                    collisionBoundary={[]}
                    onOpenAutoFocus={(e) => e.preventDefault()}
                    onWheel={(e) => e.stopPropagation()}
                >
                    {filtered.length === 0 ? (
                        <div className="px-2 py-1.5 text-sm text-muted-foreground">
                            {allowCustom ? search : 'No results'}
                        </div>
                    ) : (
                        Array.from(grouped.entries()).map(([group, opts]) => (
                            <div key={group}>
                                {group && (
                                    <div className="px-2 py-1.5 text-xs font-semibold text-muted-foreground">
                                        {group}
                                    </div>
                                )}
                                {opts.map((opt) => (
                                    <button
                                        key={opt.value}
                                        type="button"
                                        className="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent hover:text-accent-foreground"
                                        onMouseDown={(e) => {
                                            e.preventDefault();
                                            handleSelect(opt.value);
                                        }}
                                    >
                                        <Check
                                            className={cn(
                                                'size-4 shrink-0',
                                                value === opt.value ? 'opacity-100' : 'opacity-0',
                                            )}
                                        />
                                        {opt.label}
                                    </button>
                                ))}
                            </div>
                        ))
                    )}
                </Popover.Content>
            </Popover.Portal>
        </Popover.Root>
    );
}

export { Combobox };
export type { ComboboxOption };
