import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { type PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { ArrowLeft, Loader2 } from 'lucide-react';
import { type FormEvent } from 'react';

interface CreateTeamFormData {
  code: string;
  name: string;
}

/**
 * Create team form — collects code and name, submits via Inertia POST.
 */
export default function TeamsCreate(_props: PageProps) {
  const { data, setData, post, processing, errors } = useForm<CreateTeamFormData>({
    code: '',
    name: '',
  });

  const handleSubmit = (e: FormEvent<HTMLFormElement>): void => {
    e.preventDefault();
    post(route('teams.store'));
  };

  return (
    <AuthenticatedLayout
      header={
        <div className="flex items-center gap-3">
          <Link href={route('teams.index')}>
            <Button variant="ghost" size="sm" className="gap-1.5 text-muted-foreground">
              <ArrowLeft size={14} />
              Teams
            </Button>
          </Link>
          <span className="text-muted-foreground">/</span>
          <h2 className="text-lg font-semibold text-foreground">New Team</h2>
        </div>
      }
    >
      <Head title="New Team" />

      <div className="mx-auto max-w-md">
        <div className="rounded-xl border border-border bg-card p-6">
          <h3 className="font-semibold text-foreground">Create a Team</h3>
          <p className="mt-1 text-sm text-muted-foreground">
            Give your team a short code and a full name.
          </p>

          <form onSubmit={handleSubmit} className="mt-5 space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="code">Team Code</Label>
              <Input
                id="code"
                type="text"
                placeholder="e.g. LAL"
                maxLength={10}
                value={data.code}
                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                disabled={processing}
              />
              <p className="text-xs text-muted-foreground">Max 10 characters. Must be unique.</p>
              {errors.code && (
                <p className="text-xs text-destructive">{errors.code}</p>
              )}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="name">Team Name</Label>
              <Input
                id="name"
                type="text"
                placeholder="e.g. Los Angeles Lakers"
                maxLength={100}
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                disabled={processing}
              />
              {errors.name && (
                <p className="text-xs text-destructive">{errors.name}</p>
              )}
            </div>

            <div className="flex items-center gap-3 pt-2">
              <Button type="submit" disabled={processing} className="gap-2">
                {processing && <Loader2 size={14} className="animate-spin" />}
                Create Team
              </Button>
              <Link href={route('teams.index')}>
                <Button type="button" variant="ghost" disabled={processing}>
                  Cancel
                </Button>
              </Link>
            </div>
          </form>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
