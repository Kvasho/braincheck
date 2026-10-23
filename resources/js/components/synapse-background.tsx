import { cn } from '@/lib/utils';

type Node = { x: number; y: number; r: number; delay: number };
type Edge = { from: Node; to: Node };

const WIDTH = 1200;
const HEIGHT = 800;
const NODE_COUNT = 34;
const MAX_EDGE_LENGTH = 230;

/** Small deterministic PRNG so the network is identical on every render (no layout shift). */
function mulberry32(seed: number): () => number {
    let state = seed;

    return () => {
        state = (state + 0x6d2b79f5) | 0;
        let t = Math.imul(state ^ (state >>> 15), 1 | state);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;

        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

function buildNetwork(): { nodes: Node[]; edges: Edge[] } {
    const random = mulberry32(275);
    const nodes: Node[] = Array.from({ length: NODE_COUNT }, () => ({
        x: Math.round(random() * WIDTH),
        y: Math.round(random() * HEIGHT),
        r: 1.5 + random() * 2.5,
        delay: Math.round(random() * 6000),
    }));

    const edges: Edge[] = [];

    nodes.forEach((from, i) => {
        nodes.slice(i + 1).forEach((to) => {
            if (Math.hypot(from.x - to.x, from.y - to.y) < MAX_EDGE_LENGTH) {
                edges.push({ from, to });
            }
        });
    });

    return { nodes, edges };
}

const NETWORK = buildNetwork();

/**
 * Decorative "neural network" motif: subtle nodes and connections.
 * Pure decoration (aria-hidden), never intercepts input, and only pulses when
 * the user has not requested reduced motion.
 */
export function SynapseBackground({ className }: { className?: string }) {
    return (
        <svg
            aria-hidden="true"
            focusable="false"
            viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
            preserveAspectRatio="xMidYMid slice"
            className={cn(
                'pointer-events-none absolute inset-0 -z-10 size-full opacity-40 select-none dark:opacity-50',
                className,
            )}
        >
            <g
                className="stroke-synapse-line"
                strokeWidth={1}
                strokeOpacity={0.35}
            >
                {NETWORK.edges.map(({ from, to }) => (
                    <line
                        key={`${from.x}-${from.y}-${to.x}-${to.y}`}
                        x1={from.x}
                        y1={from.y}
                        x2={to.x}
                        y2={to.y}
                    />
                ))}
            </g>
            <g className="fill-synapse-node">
                {NETWORK.nodes.map((node) => (
                    <circle
                        key={`${node.x}-${node.y}`}
                        cx={node.x}
                        cy={node.y}
                        r={node.r}
                        className="motion-safe:animate-synapse-pulse"
                        style={{ animationDelay: `${node.delay}ms` }}
                    />
                ))}
            </g>
        </svg>
    );
}
