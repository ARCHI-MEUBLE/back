import Link from "next/link";

export function Hero() {
  return (
    <section className="bg-[#F9F9F9]">
      <div className="mx-auto flex min-h-[420px] max-w-5xl flex-col items-start justify-center px-6 py-20 text-left">
        <p className="text-sm font-semibold uppercase tracking-[0.2em] text-gray-500">Collection 2025</p>
        <h1 className="mt-4 max-w-3xl text-3xl font-semibold text-gray-900 md:text-4xl">
          Configurez votre meuble idéal en quelques clics
        </h1>
        <p className="mt-3 max-w-xl text-base text-gray-600">
          Choisissez un modèle existant ou créez le vôtre.
        </p>
        <Link
          href="#templates"
          className="mt-8 inline-flex items-center rounded-full border border-gray-300 px-5 py-2 text-sm font-medium text-gray-800 transition hover:bg-white"
        >
          Découvrir nos modèles
        </Link>
      </div>
    </section>
  );
}
