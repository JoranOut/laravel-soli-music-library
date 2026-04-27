import type { ReactNode } from 'react';
import {
    createContext,
    useCallback,
    useContext,
    useRef,
    useState,
} from 'react';

export type Track = {
    title: string;
    composer: string | null;
    url: string;
};

type AudioPlayerContextValue = {
    currentTrack: Track | null;
    isPlaying: boolean;
    currentTime: number;
    duration: number;
    play: (track: Track) => void;
    pause: () => void;
    toggle: (track: Track) => void;
    seek: (seconds: number) => void;
    close: () => void;
    speed: number;
    setSpeed: (rate: number) => void;
    volume: number;
    setVolume: (level: number) => void;
    isCurrentTrack: (url: string) => boolean;
};

const AudioPlayerContext = createContext<AudioPlayerContextValue | null>(null);

export function AudioPlayerProvider({ children }: { children: ReactNode }) {
    const audioRef = useRef<HTMLAudioElement | null>(null);
    const seekingRef = useRef(false);
    const [currentTrack, setCurrentTrack] = useState<Track | null>(null);
    const [isPlaying, setIsPlaying] = useState(false);
    const [currentTime, setCurrentTime] = useState(0);
    const [duration, setDuration] = useState(0);
    const [speed, setSpeedState] = useState(1);
    const [volume, setVolumeState] = useState(1);

    const getAudio = useCallback(() => {
        if (!audioRef.current) {
            const audio = new Audio();
            audio.playbackRate = speed;
            audio.volume = volume;
            audio.addEventListener('timeupdate', () => {
                if (!seekingRef.current) {
                    setCurrentTime(audio.currentTime);
                }
            });
            audio.addEventListener('seeked', () => {
                seekingRef.current = false;
                setCurrentTime(audio.currentTime);
            });
            audio.addEventListener('loadedmetadata', () => {
                setDuration(audio.duration);
            });
            audio.addEventListener('ended', () => {
                setIsPlaying(false);
            });
            audioRef.current = audio;
        }
        return audioRef.current;
    }, [speed, volume]);

    const play = useCallback(
        (track: Track) => {
            const audio = getAudio();
            if (currentTrack?.url !== track.url) {
                audio.src = track.url;
                setCurrentTime(0);
                setDuration(0);
            }
            audio.play();
            setCurrentTrack(track);
            setIsPlaying(true);
        },
        [getAudio, currentTrack],
    );

    const pause = useCallback(() => {
        audioRef.current?.pause();
        setIsPlaying(false);
    }, []);

    const toggle = useCallback(
        (track: Track) => {
            if (currentTrack?.url === track.url && isPlaying) {
                pause();
            } else {
                play(track);
            }
        },
        [currentTrack, isPlaying, pause, play],
    );

    const seek = useCallback((seconds: number) => {
        const audio = audioRef.current;
        if (audio) {
            seekingRef.current = true;
            audio.currentTime = seconds;
            setCurrentTime(seconds);
        }
    }, []);

    const setSpeed = useCallback((rate: number) => {
        setSpeedState(rate);
        if (audioRef.current) {
            audioRef.current.playbackRate = rate;
        }
    }, []);

    const setVolume = useCallback((level: number) => {
        setVolumeState(level);
        if (audioRef.current) {
            audioRef.current.volume = level;
        }
    }, []);

    const close = useCallback(() => {
        if (audioRef.current) {
            audioRef.current.pause();
            audioRef.current.removeAttribute('src');
            audioRef.current.load();
            audioRef.current = null;
        }
        setIsPlaying(false);
        setCurrentTrack(null);
        setCurrentTime(0);
        setDuration(0);
    }, []);

    const isCurrentTrack = useCallback(
        (url: string) => currentTrack?.url === url,
        [currentTrack],
    );

    return (
        <AudioPlayerContext.Provider
            value={{
                currentTrack,
                isPlaying,
                currentTime,
                duration,
                play,
                pause,
                toggle,
                seek,
                close,
                speed,
                setSpeed,
                volume,
                setVolume,
                isCurrentTrack,
            }}
        >
            {children}
        </AudioPlayerContext.Provider>
    );
}

export function useAudioPlayer(): AudioPlayerContextValue {
    const context = useContext(AudioPlayerContext);
    if (!context) {
        throw new Error(
            'useAudioPlayer must be used within an AudioPlayerProvider',
        );
    }
    return context;
}
