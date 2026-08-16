import { ComingSoon } from '@/components/coming-soon';

type Props = {
    title: string;
    description: string;
    module?: string;
};

export default function ComingSoonPage({ title, description, module }: Props) {
    return (
        <ComingSoon title={title} description={description} module={module} />
    );
}
