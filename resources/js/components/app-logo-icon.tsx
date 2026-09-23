import type { SVGAttributes } from 'react';

/**
 * CIDASH mark: four dashboard tiles, one highlighted. Neutral placeholder until
 * the team chooses its visual identity; uses currentColor.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <rect x="4" y="4" width="14" height="14" rx="3" />
            <rect x="22" y="4" width="14" height="14" rx="3" opacity="0.45" />
            <rect x="4" y="22" width="14" height="14" rx="3" opacity="0.45" />
            <rect x="22" y="22" width="14" height="14" rx="3" opacity="0.45" />
        </svg>
    );
}
