interface Props {
    className?: string;
    style?: React.CSSProperties;
}

export default function AppLogoIcon({ className, style }: Props) {
    return (
        <img src="/anden_logo.png" alt="El Anden" className={className} style={style} />
    );
}
