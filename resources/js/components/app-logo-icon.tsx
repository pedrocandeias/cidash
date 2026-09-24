import type { SVGAttributes } from 'react';

/**
 * The CIDASH mark: four square tiles of a dashboard, the top-right one in the
 * brand bronze. The other tiles take currentColor (ink on light, paper on dark).
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
            <rect
                x="0"
                y="0"
                width="21.12"
                height="21.12"
                fill="currentColor"
            />
            <rect
                x="26.88"
                y="0"
                width="21.12"
                height="21.12"
                style={{ fill: 'var(--bronze)' }}
            />
            <rect
                x="0"
                y="26.88"
                width="21.12"
                height="21.12"
                fill="currentColor"
            />
            <rect
                x="26.88"
                y="26.88"
                width="21.12"
                height="21.12"
                fill="currentColor"
            />
        </svg>
    );
}
