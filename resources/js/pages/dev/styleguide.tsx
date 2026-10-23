import { Head } from '@inertiajs/react';
import { CircleAlert, CircleCheck, Info, TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Logo, LogoMark } from '@/components/logo';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

/** Literal class names so Tailwind generates every swatch. */
const swatches = [
    { name: 'background', className: 'bg-background text-foreground' },
    { name: 'card', className: 'bg-card text-card-foreground' },
    { name: 'muted', className: 'bg-muted text-muted-foreground' },
    { name: 'secondary', className: 'bg-secondary text-secondary-foreground' },
    { name: 'accent', className: 'bg-accent text-accent-foreground' },
    { name: 'primary', className: 'bg-primary text-primary-foreground' },
    { name: 'success', className: 'bg-success text-success-foreground' },
    { name: 'warning', className: 'bg-warning text-warning-foreground' },
    {
        name: 'destructive',
        className: 'bg-destructive text-destructive-foreground',
    },
    { name: 'border', className: 'bg-border text-foreground' },
    { name: 'input', className: 'bg-input text-background' },
    { name: 'ring', className: 'bg-ring text-background' },
];

const charts = [
    'bg-chart-1',
    'bg-chart-2',
    'bg-chart-3',
    'bg-chart-4',
    'bg-chart-5',
];

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="space-y-4">
            <h2 className="text-xl font-semibold tracking-tight">{title}</h2>
            {children}
        </section>
    );
}

export default function Styleguide() {
    return (
        <>
            <Head title="Style guide" />

            <div className="space-y-12 py-10">
                <header className="space-y-2">
                    <Badge variant="warning">Local only</Badge>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Synapse style guide
                    </h1>
                    <p className="text-muted-foreground">
                        Design tokens and components. Toggle the theme in
                        Settings → Appearance to check both themes.
                    </p>
                </header>

                <Section title="Brand">
                    <div className="flex flex-wrap items-center gap-8 rounded-xl border bg-card p-6">
                        <Logo />
                        <Logo showName={false} />
                        <LogoMark className="size-12" aria-hidden="true" />
                    </div>
                </Section>

                <Section title="Colours">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        {swatches.map(({ name, className }) => (
                            <div
                                key={name}
                                className={`flex h-20 items-end rounded-lg border p-3 text-sm font-medium ${className}`}
                            >
                                {name}
                            </div>
                        ))}
                    </div>
                    <div className="flex gap-2">
                        {charts.map((className, index) => (
                            <div
                                key={className}
                                className={`h-10 flex-1 rounded-md ${className}`}
                                title={`chart-${index + 1}`}
                            />
                        ))}
                    </div>
                </Section>

                <Section title="Typography">
                    <div className="space-y-3 rounded-xl border bg-card p-6">
                        <p className="text-4xl font-bold">Heading 700</p>
                        <p className="text-2xl font-semibold">Heading 600</p>
                        <p className="text-lg font-medium">
                            Medium 500 — ტვინის შემოწმება
                        </p>
                        <p>
                            Regular 400 — The quick brown fox jumps over the
                            lazy dog. სწრაფი ყავისფერი მელა ზარმაც ძაღლს
                            გადაახტა.
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Muted small text for hints and descriptions.
                        </p>
                        <div className="flex items-baseline gap-6">
                            <span className="tabular text-3xl">12:04</span>
                            <span className="tabular text-3xl text-success">
                                98 / 100
                            </span>
                            <span className="text-sm text-muted-foreground">
                                JetBrains Mono, tabular-nums
                            </span>
                        </div>
                    </div>
                </Section>

                <Section title="Buttons">
                    <div className="flex flex-wrap items-center gap-3">
                        <Button>Primary</Button>
                        <Button variant="secondary">Secondary</Button>
                        <Button variant="outline">Outline</Button>
                        <Button variant="ghost">Ghost</Button>
                        <Button variant="link">Link</Button>
                        <Button variant="destructive">Destructive</Button>
                        <Button disabled>Disabled</Button>
                        <Button size="sm">Small</Button>
                        <Button size="lg">Large</Button>
                    </div>
                </Section>

                <Section title="Inputs">
                    <div className="grid max-w-md gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="sg-name">Name</Label>
                            <Input id="sg-name" placeholder="Nino Beridze" />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="sg-email">Email (invalid)</Label>
                            <Input
                                id="sg-email"
                                defaultValue="not-an-email"
                                aria-invalid="true"
                            />
                            <InputError message="The email field must be a valid email address." />
                        </div>
                        <div className="grid gap-2">
                            <Label>Question type</Label>
                            <Select defaultValue="single">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="single">
                                        Single choice
                                    </SelectItem>
                                    <SelectItem value="multiple">
                                        Multiple choice
                                    </SelectItem>
                                    <SelectItem value="text">
                                        Short text
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex items-center gap-2">
                            <Checkbox id="sg-remember" />
                            <Label htmlFor="sg-remember">Remember me</Label>
                        </div>
                        <Input disabled placeholder="Disabled" />
                    </div>
                </Section>

                <Section title="Cards">
                    <div className="grid gap-4 md:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Algebra basics</CardTitle>
                                <CardDescription>
                                    20 questions · 30 minutes
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="text-sm">
                                Card content goes here.
                            </CardContent>
                            <CardFooter className="gap-2">
                                <Button size="sm">Start</Button>
                                <Button size="sm" variant="outline">
                                    Details
                                </Button>
                            </CardFooter>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Score</CardTitle>
                                <CardDescription>Last attempt</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <span className="tabular text-4xl text-primary">
                                    87%
                                </span>
                            </CardContent>
                        </Card>
                    </div>
                </Section>

                <Section title="Badges">
                    <div className="flex flex-wrap gap-2">
                        <Badge>Primary</Badge>
                        <Badge variant="secondary">Secondary</Badge>
                        <Badge variant="success">Passed</Badge>
                        <Badge variant="warning">Pending</Badge>
                        <Badge variant="destructive">Failed</Badge>
                        <Badge variant="outline">Outline</Badge>
                    </div>
                </Section>

                <Section title="Alerts">
                    <div className="grid gap-3">
                        <Alert>
                            <Info />
                            <AlertTitle>Default</AlertTitle>
                            <AlertDescription>
                                Neutral information.
                            </AlertDescription>
                        </Alert>
                        <Alert variant="success">
                            <CircleCheck />
                            <AlertTitle>Success</AlertTitle>
                            <AlertDescription>
                                Your answers were saved.
                            </AlertDescription>
                        </Alert>
                        <Alert variant="warning">
                            <TriangleAlert />
                            <AlertTitle>Warning</AlertTitle>
                            <AlertDescription>
                                Five minutes left.
                            </AlertDescription>
                        </Alert>
                        <Alert variant="destructive">
                            <CircleAlert />
                            <AlertTitle>Error</AlertTitle>
                            <AlertDescription>
                                Something went wrong.
                            </AlertDescription>
                        </Alert>
                    </div>
                </Section>

                <Section title="Toasts">
                    <div className="flex flex-wrap gap-3">
                        <Button
                            variant="outline"
                            onClick={() => toast('Neutral toast')}
                        >
                            Default
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => toast.success('Saved')}
                        >
                            Success
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => toast.warning('Five minutes left')}
                        >
                            Warning
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => toast.error('Something went wrong')}
                        >
                            Error
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => toast.info('Heads up')}
                        >
                            Info
                        </Button>
                    </div>
                </Section>
            </div>
        </>
    );
}
