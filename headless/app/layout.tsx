import type { Metadata } from "next";
import { Oswald, Inter } from "next/font/google";
import Link from "next/link";
import { siteConfig } from "@/site.config";
import { mainMenu } from "@/menu.config";
import "./globals.css";

const headline = Oswald({
  subsets: ["latin"],
  weight: ["400", "500", "600", "700"],
  variable: "--font-headline",
});

const body = Inter({
  subsets: ["latin"],
  variable: "--font-body",
});

export const metadata: Metadata = {
  title: siteConfig.site_name,
  description: siteConfig.site_description,
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" className={`${headline.variable} ${body.variable}`}>
      <body className="flex min-h-screen flex-col bg-wg-ivory text-wg-charcoal antialiased">
        <header className="border-b border-wg-sepia/40 bg-wg-espresso">
          <nav className="mx-auto flex max-w-7xl flex-wrap items-center gap-x-8 gap-y-3 px-6 py-4">
            <Link
              href="/"
              className="mr-auto font-headline text-lg font-semibold tracking-wide text-wg-ivory no-underline"
            >
              {siteConfig.site_name}
            </Link>
            <div className="flex flex-wrap gap-x-6 gap-y-2">
              {Object.entries(mainMenu).map(([label, href]) => (
                <Link
                  key={href}
                  href={href}
                  className="font-headline text-sm font-semibold uppercase tracking-wider text-wg-muted no-underline hover:text-wg-ivory"
                >
                  {label}
                </Link>
              ))}
            </div>
          </nav>
        </header>
        <main className="mx-auto w-full max-w-7xl flex-1 px-6 py-12">{children}</main>
        <footer className="border-t border-wg-sepia/40 bg-wg-espresso px-6 py-6 text-center text-sm text-wg-muted">
          {siteConfig.site_name}
        </footer>
      </body>
    </html>
  );
}
