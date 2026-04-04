import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { type PlayerWithStats } from '@/types';
import { useForm } from '@inertiajs/react';
import { Camera } from 'lucide-react';

interface ProfilePictureUploadProps {
    player: PlayerWithStats;
}

/**
 * Profile picture upload for the edit sheet (edit mode only).
 * Uses a button + Inertia post — not a nested <form> — because PlayerFormSheet
 * already wraps content in a form; nested forms are invalid HTML and browsers
 * merge submits with the parent form (PUT update instead of picture POST).
 */
export function ProfilePictureUpload({ player }: ProfilePictureUploadProps) {
    const { data, setData, post, processing, errors, reset } = useForm<{ picture: File | null }>({
        picture: null,
    });

    const handleUpload = (): void => {
        if (!data.picture) return;
        post(route('players.uploadPicture', { player: player.id }), {
            forceFormData: true,
            onSuccess: () => reset(),
        });
    };

    const pictureUrl = player.profile_picture_path
        ? `/storage/${player.profile_picture_path}`
        : null;

    return (
        <div className="space-y-3">
            <div className="flex items-center gap-4">
                {/* Avatar preview */}
                <div className="relative h-16 w-16 shrink-0 rounded-full overflow-hidden border border-border bg-muted flex items-center justify-center">
                    {pictureUrl ? (
                        <img
                            key={player.profile_picture_path ?? 'avatar'}
                            src={pictureUrl}
                            alt={`${player.first_name} ${player.last_name}`}
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <Camera size={20} className="text-muted-foreground" />
                    )}
                </div>

                <div className="flex-1 space-y-1.5">
                    <Label htmlFor="picture" className="text-xs">Profile Picture</Label>
                    <Input
                        id="picture"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="h-8 text-sm cursor-pointer"
                        onChange={(e) => setData('picture', e.target.files?.[0] ?? null)}
                    />
                    {errors.picture && (
                        <p className="text-xs text-destructive">{errors.picture}</p>
                    )}
                    <p className="text-xs text-muted-foreground">JPEG, PNG or WebP · max 2 MB</p>
                </div>
            </div>

            <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={!data.picture || processing}
                className="w-full"
                onClick={handleUpload}
            >
                {processing ? 'Uploading…' : 'Upload Picture'}
            </Button>
        </div>
    );
}
