{{--
  The WhatsApp mark, drawn as the official glyph path from the new_ui designs.

  One path, filled with currentColor, so the same mark serves the green circle on
  a notification card, the white fill on a filled button, and the ink fill on a
  footer link without a second asset.

  Sits beside components/themes/shared/whatsapp-icon.blade.php, which is the
  storefront's own mark; this one is the platform-side component.
--}}
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
     @class(['w-4 h-4', 'rotate-90' => $rotate ?? false]) aria-hidden="true" focusable="false">
  <path d="M17.472 14.382c-.301-.15-1.78-.878-2.056-.98-.276-.101-.477-.15-.678.15-.2.302-.779.98-.955 1.18-.176.202-.352.226-.653.076-.301-.15-1.272-.469-2.424-1.496-.896-.799-1.501-1.787-1.677-2.088-.176-.302-.019-.465.132-.615.136-.135.301-.352.452-.528.15-.175.2-.299.301-.502.101-.2.05-.376-.025-.526-.075-.15-.678-1.634-.93-2.238-.243-.589-.49-.509-.678-.519-.176-.01-.377-.01-.578-.01s-.527.075-.803.376c-.276.301-1.054 1.03-1.054 2.512s1.08 2.914 1.23 3.115c.15.201 2.126 3.245 5.15 4.553.72.311 1.282.497 1.72.636.723.23 1.381.197 1.901.12.579-.087 1.78-.728 2.032-1.431.251-.703.251-1.306.176-1.431-.076-.126-.277-.202-.578-.352z"/>
</svg>