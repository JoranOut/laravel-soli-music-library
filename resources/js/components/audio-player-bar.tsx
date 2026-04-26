import { Pause, Play, Volume2, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Slider } from '@/components/ui/slider';
import { useAudioPlayer } from '@/hooks/use-audio-player';

const SPEED_OPTIONS = [0.8, 0.9, 1, 1.2] as const;

function formatTime(seconds: number): string {
    if (!isFinite(seconds) || seconds < 0) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

function nextSpeed(current: number): number {
    const idx = SPEED_OPTIONS.indexOf(current as (typeof SPEED_OPTIONS)[number]);
    return SPEED_OPTIONS[(idx + 1) % SPEED_OPTIONS.length];
}

export function AudioPlayerBar() {
    const {
        currentTrack,
        isPlaying,
        currentTime,
        duration,
        toggle,
        seek,
        close,
        speed,
        setSpeed,
        volume,
        setVolume,
    } = useAudioPlayer();

    if (!currentTrack) return null;

    return (
        <div className="sticky bottom-0 z-50 border-t bg-background">
            <div className="flex items-center gap-3 px-4 py-2">
                {/* Track info */}
                <div className="min-w-0 shrink-0 max-w-[150px] sm:max-w-[200px]">
                    <p className="truncate text-sm font-medium">
                        {currentTrack?.title}
                    </p>
                    {currentTrack?.composer && (
                        <p className="truncate text-xs text-muted-foreground">
                            {currentTrack.composer}
                        </p>
                    )}
                </div>

                {/* Play/pause */}
                <Button
                    variant="ghost"
                    size="icon"
                    className="shrink-0"
                    onClick={() => currentTrack && toggle(currentTrack)}
                >
                    {isPlaying ? <Pause /> : <Play />}
                </Button>

                {/* Seek slider */}
                <Slider
                    className="flex-1"
                    min={0}
                    max={duration || 1}
                    step={0.1}
                    value={[currentTime]}
                    onValueChange={([value]) => seek(value)}
                />

                {/* Time display */}
                <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
                    {formatTime(currentTime)} / {formatTime(duration)}
                </span>

                {/* Speed toggle */}
                <Button
                    variant="outline"
                    size="sm"
                    className="shrink-0 w-14 tabular-nums text-xs"
                    onClick={() => setSpeed(nextSpeed(speed))}
                >
                    {speed}x
                </Button>

                {/* Volume */}
                <div className="hidden shrink-0 items-center gap-1.5 sm:flex">
                    <Volume2 className="size-4 text-muted-foreground" />
                    <Slider
                        className="w-20"
                        min={0}
                        max={1}
                        step={0.01}
                        value={[volume]}
                        onValueChange={([value]) => setVolume(value)}
                    />
                </div>

                {/* Close */}
                <Button
                    variant="ghost"
                    size="icon"
                    className="shrink-0"
                    onClick={close}
                >
                    <X />
                </Button>
            </div>
        </div>
    );
}
