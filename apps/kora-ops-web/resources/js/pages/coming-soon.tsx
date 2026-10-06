import { ComingSoon } from '@/components/coming-soon';

type Props = {
    title: string;
    description: string;
    module?: string;
    backHref?: string;
    backLabel?: string;
};

export default function ComingSoonPage({
    title,
    description,
    module,
    backHref,
    backLabel,
}: Props) {
    return (
        <ComingSoon
            title={title}
            description={description}
            module={module}
            backHref={backHref}
            backLabel={backLabel}
        />
    );
}
