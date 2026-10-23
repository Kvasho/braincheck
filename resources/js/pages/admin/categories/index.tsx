import type { DragEndEvent } from '@dnd-kit/core';
import {
    closestCenter,
    DndContext,
    KeyboardSensor,
    PointerSensor,
    TouchSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Form, Head, router } from '@inertiajs/react';
import { FolderTree, GripVertical, Pencil, Plus, Trash2 } from 'lucide-react';
import { useReducedMotion } from 'motion/react';
import { createContext, useContext, useState } from 'react';
import { toast } from 'sonner';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import { ConfirmDialog } from '@/components/data/confirm-dialog';
import { EmptyState } from '@/components/data/empty-state';
import { PageHeader } from '@/components/data/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import type { CategoryNode } from '@/types';

const NO_PARENT = '__none__';

/** Mirrors Str::slug() closely enough for a placeholder (the server decides). */
function slugify(value: string): string {
    return value
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

type Editing = { mode: 'create' } | { mode: 'edit'; category: CategoryNode };

export default function CategoriesIndex({ tree }: { tree: CategoryNode[] }) {
    const { t } = useTranslation();
    const [items, setItems] = useState(tree);
    const [syncedTree, setSyncedTree] = useState(tree);
    const [editing, setEditing] = useState<Editing | null>(null);
    const [deleting, setDeleting] = useState<CategoryNode | null>(null);
    const [processing, setProcessing] = useState(false);

    // A server response replaces the optimistic order.
    if (tree !== syncedTree) {
        setSyncedTree(tree);
        setItems(tree);
    }

    const persistOrder = (parent: string | null, order: string[]) => {
        router.put(
            admin.categories.reorder.url(),
            { parent, order },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) => {
                    setItems(tree);
                    toast.error(
                        errors.order ?? t('The order could not be saved.'),
                    );
                },
            },
        );
    };

    const reorderTop = (activeId: string, overId: string) => {
        const from = items.findIndex((item) => item.ulid === activeId);
        const to = items.findIndex((item) => item.ulid === overId);

        if (from < 0 || to < 0) {
            return;
        }

        const next = arrayMove(items, from, to);
        setItems(next);
        persistOrder(
            null,
            next.map((item) => item.ulid),
        );
    };

    const reorderChildren = (
        parent: CategoryNode,
        activeId: string,
        overId: string,
    ) => {
        const from = parent.children.findIndex((c) => c.ulid === activeId);
        const to = parent.children.findIndex((c) => c.ulid === overId);

        if (from < 0 || to < 0) {
            return;
        }

        const children = arrayMove(parent.children, from, to);
        setItems(
            items.map((item) =>
                item.ulid === parent.ulid ? { ...item, children } : item,
            ),
        );
        persistOrder(
            parent.ulid,
            children.map((child) => child.ulid),
        );
    };

    const deleteCategory = () => {
        if (!deleting) {
            return;
        }

        router.delete(admin.categories.destroy.url(deleting.ulid), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setDeleting(null),
            onError: (errors) => {
                setDeleting(null);
                toast.error(
                    errors.category ?? t('The category could not be deleted.'),
                );
            },
        });
    };

    const usage = deleting
        ? deleting.questions_count + deleting.tests_count
        : 0;

    return (
        <>
            <Head title={t('Categories')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('Categories')}
                    description={t(
                        'Two levels: categories and subcategories. Drag the handle (or focus it and use the arrow keys) to reorder within a level.',
                    )}
                    actions={
                        <Button onClick={() => setEditing({ mode: 'create' })}>
                            <Plus aria-hidden="true" />
                            {t('New category')}
                        </Button>
                    }
                />

                {items.length === 0 ? (
                    <EmptyState
                        icon={FolderTree}
                        title={t('No categories yet')}
                        description={t(
                            'Create the first category to organise questions and tests.',
                        )}
                    />
                ) : (
                    <SortableLevel
                        items={items}
                        label={t('Categories')}
                        onReorder={reorderTop}
                        renderItem={(category) => (
                            <>
                                <CategoryRow
                                    category={category}
                                    onEdit={() =>
                                        setEditing({ mode: 'edit', category })
                                    }
                                    onDelete={() => setDeleting(category)}
                                />
                                {category.children.length > 0 && (
                                    <div className="mt-2 ml-8 border-l pl-3">
                                        <SortableLevel
                                            items={category.children}
                                            label={t('Subcategories of :name', {
                                                name: category.name.en,
                                            })}
                                            onReorder={(activeId, overId) =>
                                                reorderChildren(
                                                    category,
                                                    activeId,
                                                    overId,
                                                )
                                            }
                                            renderItem={(child) => (
                                                <CategoryRow
                                                    category={child}
                                                    onEdit={() =>
                                                        setEditing({
                                                            mode: 'edit',
                                                            category: child,
                                                        })
                                                    }
                                                    onDelete={() =>
                                                        setDeleting(child)
                                                    }
                                                />
                                            )}
                                        />
                                    </div>
                                )}
                            </>
                        )}
                    />
                )}
            </div>

            <CategoryDialog
                editing={editing}
                tree={items}
                onClose={() => setEditing(null)}
            />

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(next) => !next && setDeleting(null)}
                title={t('Delete :name?', { name: deleting?.name.en ?? '' })}
                description={
                    usage > 0
                        ? t(
                              'It is used by :questions questions and :tests tests. They will keep existing without a category.',
                              {
                                  questions: deleting?.questions_count ?? 0,
                                  tests: deleting?.tests_count ?? 0,
                              },
                          )
                        : t('This category is not used anywhere.')
                }
                confirmLabel={t('Delete category')}
                destructive
                confirmText={usage > 0 ? deleting?.slug : undefined}
                processing={processing}
                onConfirm={deleteCategory}
            />
        </>
    );
}

function SortableLevel({
    items,
    label,
    onReorder,
    renderItem,
}: {
    items: CategoryNode[];
    label: string;
    onReorder: (activeId: string, overId: string) => void;
    renderItem: (item: CategoryNode) => React.ReactNode;
}) {
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
        useSensor(TouchSensor, {
            activationConstraint: { delay: 150, tolerance: 5 },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        if (over && active.id !== over.id) {
            onReorder(String(active.id), String(over.id));
        }
    };

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={onDragEnd}
        >
            <SortableContext
                items={items.map((item) => item.ulid)}
                strategy={verticalListSortingStrategy}
            >
                <ul className="space-y-2" aria-label={label}>
                    {items.map((item) => (
                        <SortableItem key={item.ulid} id={item.ulid}>
                            {renderItem(item)}
                        </SortableItem>
                    ))}
                </ul>
            </SortableContext>
        </DndContext>
    );
}

function SortableItem({
    id,
    children,
}: {
    id: string;
    children: React.ReactNode;
}) {
    const reduceMotion = useReducedMotion();
    const {
        setNodeRef,
        transform,
        transition,
        isDragging,
        attributes,
        listeners,
        setActivatorNodeRef,
    } = useSortable({ id });

    return (
        <li
            ref={setNodeRef}
            style={{
                transform: CSS.Translate.toString(transform),
                transition: reduceMotion ? undefined : transition,
            }}
            className={cn(isDragging && 'relative z-10 opacity-80')}
        >
            <DragHandleContext.Provider
                value={{ attributes, listeners, setActivatorNodeRef }}
            >
                {children}
            </DragHandleContext.Provider>
        </li>
    );
}

type DragHandleValue = Pick<
    ReturnType<typeof useSortable>,
    'attributes' | 'listeners' | 'setActivatorNodeRef'
>;

/** Lets the row render the drag handle that SortableItem owns. */
const DragHandleContext = createContext<DragHandleValue | null>(null);

function CategoryRow({
    category,
    onEdit,
    onDelete,
}: {
    category: CategoryNode;
    onEdit: () => void;
    onDelete: () => void;
}) {
    const { t, locale } = useTranslation();
    const handle = useContext(DragHandleContext);
    const otherLocale = locale === 'ka' ? 'en' : 'ka';

    return (
        <div className="flex items-center gap-3 rounded-xl border bg-card p-3 text-card-foreground">
            <button
                type="button"
                ref={handle?.setActivatorNodeRef}
                {...handle?.attributes}
                {...handle?.listeners}
                aria-label={t('Reorder :name', { name: category.name[locale] })}
                className="cursor-grab touch-none rounded p-1 text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:cursor-grabbing"
            >
                <GripVertical className="size-4" aria-hidden="true" />
            </button>

            <div className="min-w-0 flex-1">
                <p className="truncate font-medium">{category.name[locale]}</p>
                <p className="truncate text-sm text-muted-foreground">
                    {category.name[otherLocale]} ·{' '}
                    <span className="font-mono">{category.slug}</span>
                </p>
            </div>

            <p className="tabular hidden text-sm text-muted-foreground sm:block">
                {t(':questions questions · :tests tests', {
                    questions: category.questions_count,
                    tests: category.tests_count,
                })}
            </p>

            <Button
                variant="ghost"
                size="icon"
                onClick={onEdit}
                aria-label={t('Edit :name', { name: category.name[locale] })}
            >
                <Pencil aria-hidden="true" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                onClick={onDelete}
                disabled={category.children.length > 0}
                title={
                    category.children.length > 0
                        ? t('Delete or move its subcategories first.')
                        : undefined
                }
                aria-label={t('Delete :name', { name: category.name[locale] })}
            >
                <Trash2 aria-hidden="true" />
            </Button>
        </div>
    );
}

function CategoryDialog({
    editing,
    tree,
    onClose,
}: {
    editing: Editing | null;
    tree: CategoryNode[];
    onClose: () => void;
}) {
    const { t, locale } = useTranslation();
    const category = editing?.mode === 'edit' ? editing.category : null;
    const [english, setEnglish] = useState('');
    const [parent, setParent] = useState<string | null>(null);
    const [openedFor, setOpenedFor] = useState<Editing | null>(null);

    // Reset the fields each time the dialog opens for another category.
    if (editing !== openedFor) {
        setOpenedFor(editing);
        setEnglish(category?.name.en ?? '');
        setParent(
            category
                ? (tree.find((item) =>
                      item.children.some((c) => c.ulid === category.ulid),
                  )?.ulid ?? null)
                : null,
        );
    }

    const hasChildren = (category?.children.length ?? 0) > 0;
    const parents = tree.filter((item) => item.ulid !== category?.ulid);
    const form = category
        ? CategoryController.update.form(category.ulid)
        : CategoryController.store.form();

    return (
        <Dialog
            open={editing !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <Form
                    {...form}
                    key={category?.ulid ?? 'new'}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {category
                                        ? t('Edit category')
                                        : t('New category')}
                                </DialogTitle>
                                <DialogDescription>
                                    {t(
                                        'Both names are required. The slug is generated from the English name unless you set one.',
                                    )}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="name-ka">
                                    {t('Name (Georgian)')}
                                </Label>
                                <Input
                                    id="name-ka"
                                    name="name[ka]"
                                    lang="ka"
                                    required
                                    maxLength={100}
                                    defaultValue={category?.name.ka}
                                />
                                <InputError message={errors['name.ka']} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name-en">
                                    {t('Name (English)')}
                                </Label>
                                <Input
                                    id="name-en"
                                    name="name[en]"
                                    lang="en"
                                    required
                                    maxLength={100}
                                    value={english}
                                    onChange={(event) =>
                                        setEnglish(event.target.value)
                                    }
                                />
                                <InputError message={errors['name.en']} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="slug">{t('Slug')}</Label>
                                <Input
                                    id="slug"
                                    name="slug"
                                    className="font-mono"
                                    maxLength={100}
                                    pattern="[a-z0-9]+(-[a-z0-9]+)*"
                                    defaultValue={category?.slug}
                                    placeholder={slugify(english) || 'category'}
                                />
                                <InputError message={errors.slug} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="parent">
                                    {t('Parent category')}
                                </Label>
                                <input
                                    type="hidden"
                                    name="parent"
                                    value={parent ?? ''}
                                />
                                <Select
                                    value={parent ?? NO_PARENT}
                                    onValueChange={(value) =>
                                        setParent(
                                            value === NO_PARENT ? null : value,
                                        )
                                    }
                                    disabled={hasChildren}
                                >
                                    <SelectTrigger id="parent">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NO_PARENT}>
                                            {t('None (top level)')}
                                        </SelectItem>
                                        {parents.map((option) => (
                                            <SelectItem
                                                key={option.ulid}
                                                value={option.ulid}
                                            >
                                                {option.name[locale]}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {hasChildren && (
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'A category with subcategories stays at the top level.',
                                        )}
                                    </p>
                                )}
                                <InputError message={errors.parent} />
                            </div>

                            <DialogFooter className="gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={onClose}
                                >
                                    {t('Cancel')}
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {t('Save')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

CategoriesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Categories', href: admin.categories.index() },
    ],
};
