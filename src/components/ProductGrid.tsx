import { useCallback, useEffect, useState } from "react";
import { fetchTemplates } from "@/lib/api";
import { ProductCard, ProductTemplate } from "@/components/ProductCard";

export function ProductGrid() {
  const [templates, setTemplates] = useState<ProductTemplate[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadTemplates = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);
      const data = await fetchTemplates();
      setTemplates(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Une erreur est survenue");
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadTemplates();
  }, [loadTemplates]);

  const renderContent = () => {
    if (isLoading) {
      return (
        <div className="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {Array.from({ length: 8 }).map((_, index) => (
            <div
              key={index}
              className="flex animate-pulse flex-col space-y-4"
            >
              <div className="aspect-[4/3] w-full rounded-2xl bg-gray-200" />
              <div className="space-y-3">
                <div className="h-4 w-2/3 rounded bg-gray-200" />
                <div className="h-3 w-1/3 rounded bg-gray-200" />
                <div className="h-8 w-1/2 rounded-full bg-gray-200" />
              </div>
            </div>
          ))}
        </div>
      );
    }

    if (error) {
      return (
        <div className="flex flex-col items-center space-y-4 py-16 text-center">
          <p className="text-base font-medium text-gray-700">{error}</p>
          <button
            type="button"
            onClick={loadTemplates}
            className="rounded-full border border-gray-300 px-4 py-2 text-sm font-medium text-gray-800 transition hover:bg-gray-50"
          >
            Réessayer
          </button>
        </div>
      );
    }

    if (templates.length === 0) {
      return (
        <p className="py-16 text-center text-base text-gray-600">Aucun modèle disponible pour le moment.</p>
      );
    }

    return (
      <div className="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        {templates.map((template) => (
          <ProductCard key={template.id} template={template} />
        ))}
      </div>
    );
  };

  return (
    <section id="templates" className="mx-auto max-w-6xl px-6 py-12">
      <div className="mb-10 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-gray-900">Nos modèles les plus demandés</h2>
          <p className="mt-2 text-sm text-gray-600">
            Inspirez-vous des créations de nos artisans pour imaginer votre prochain meuble sur mesure.
          </p>
        </div>
        <span className="text-sm text-gray-500">
          {isLoading ? "Chargement…" : `${templates.length} modèles`}
        </span>
      </div>
      {renderContent()}
    </section>
  );
}
