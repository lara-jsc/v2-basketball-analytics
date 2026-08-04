import { playerName } from './live-game-utils';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from '@/Components/ui/sheet';
import type { Player } from '@/types';
import { ArrowRightLeft, Users } from 'lucide-react';
import { useMemo, useState } from 'react';

interface BenchSubstitutionProps {
    players: Player[];
    activePlayerIds: number[];
    disabled: boolean;
    onSubstitute: (playerOutId: number, playerInId: number) => void;
}

export function BenchSubstitution({ players, activePlayerIds, disabled, onSubstitute }: BenchSubstitutionProps) {
    const [open, setOpen] = useState(false);
    const active = useMemo(() => players.filter((player) => activePlayerIds.includes(player.id)), [players, activePlayerIds]);
    const bench = useMemo(() => players.filter((player) => !activePlayerIds.includes(player.id)), [players, activePlayerIds]);
    const [playerOutId, setPlayerOutId] = useState<number | null>(null);
    const [playerInId, setPlayerInId] = useState<number | null>(null);

    function commit(): void {
        if (playerOutId === null || playerInId === null) return;
        onSubstitute(playerOutId, playerInId);
        setOpen(false);
        setPlayerOutId(null);
        setPlayerInId(null);
    }

    return (
        <section className="rounded-lg border border-border bg-card p-4" aria-labelledby="bench-heading">
            <div className="flex items-center justify-between gap-3">
                <div><h2 id="bench-heading" className="flex items-center gap-2 text-sm font-bold uppercase tracking-[0.1em] text-foreground"><Users size={16} className="text-amber-300" /> Bench & substitutions</h2><p className="mt-1 text-xs text-muted-foreground">{bench.length} available off court</p></div>
                <Sheet open={open} onOpenChange={setOpen}>
                    <SheetTrigger asChild><button type="button" disabled={disabled || bench.length === 0 || active.length === 0} className="flex h-9 items-center gap-2 rounded-md border border-amber-300/40 bg-amber-300/10 px-3 text-xs font-bold uppercase tracking-wide text-amber-100 transition-colors hover:bg-amber-300/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:opacity-50"><ArrowRightLeft size={15} /> Sub</button></SheetTrigger>
                    <SheetContent side="left" className="border-border bg-background p-5">
                        <SheetHeader><SheetTitle className="font-display uppercase tracking-wide">Make substitution</SheetTitle><SheetDescription>Select the player leaving the court and the player entering.</SheetDescription></SheetHeader>
                        <div className="mt-6 grid gap-5">
                            <PlayerChoice label="Player out" players={active} selectedId={playerOutId} onChange={setPlayerOutId} />
                            <PlayerChoice label="Player in" players={bench} selectedId={playerInId} onChange={setPlayerInId} />
                            <button type="button" onClick={commit} disabled={disabled || playerOutId === null || playerInId === null} className="flex h-11 items-center justify-center gap-2 rounded-md bg-amber-400 px-4 text-sm font-bold uppercase tracking-wide text-black transition-colors hover:bg-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:opacity-50"><ArrowRightLeft size={16} /> Confirm substitution</button>
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
            <div className="mt-3 grid grid-cols-2 gap-2">
                {bench.slice(0, 6).map((player) => <div key={player.id} className="min-w-0 rounded-md bg-muted/30 px-2 py-2 text-sm text-muted-foreground"><span className="mr-2 font-mono text-amber-200">{player.jersey_number}</span><span className="truncate">{playerName(player)}</span></div>)}
                {bench.length === 0 && <p className="col-span-2 text-sm text-muted-foreground">No bench players available.</p>}
            </div>
        </section>
    );
}

function PlayerChoice({ label, players, selectedId, onChange }: { label: string; players: Player[]; selectedId: number | null; onChange: (id: number) => void }) {
    return <fieldset><legend className="mb-2 text-xs font-bold uppercase tracking-wide text-muted-foreground">{label}</legend><div className="grid gap-2">{players.map((player) => <label key={player.id} className={`flex min-h-12 cursor-pointer items-center gap-3 rounded-md border px-3 transition-colors ${selectedId === player.id ? 'border-cyan-300/70 bg-cyan-300/10' : 'border-border hover:bg-muted/40'}`}><input type="radio" name={label} checked={selectedId === player.id} onChange={() => onChange(player.id)} className="h-4 w-4 accent-cyan-400" /><span className="font-mono text-amber-200">{player.jersey_number}</span><span className="min-w-0 truncate text-sm font-semibold text-foreground">{playerName(player)}</span></label>)}</div></fieldset>;
}
