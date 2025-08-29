import type { NextConfig } from "next";

const nextConfig: NextConfig = {
    /* config options here */
    async rewrites() {
        const target = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000";
        return [
            {
                source: "/api/:path*",
                destination: `${target}/api/:path*`,
            },
        ];
    },
};

export default nextConfig;
