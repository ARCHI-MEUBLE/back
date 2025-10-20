import type { ProductTemplate } from "@/components/ProductCard";

export async function fetchTemplates(): Promise<ProductTemplate[]> {
  const res = await fetch("/api/templates");
  if (!res.ok) {
    throw new Error("Erreur de chargement");
  }

  const data = (await res.json()) as ProductTemplate[];
  return data;
}
