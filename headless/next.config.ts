import type { NextConfig } from "next";

const wordpressHostname = process.env.WORDPRESS_HOSTNAME || "localhost";

const nextConfig: NextConfig = {
  images: {
    remotePatterns: [
      {
        protocol: "https",
        hostname: wordpressHostname,
      },
      {
        protocol: "http",
        hostname: wordpressHostname,
      },
    ],
  },
};

export default nextConfig;
