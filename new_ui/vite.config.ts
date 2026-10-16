import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import {defineConfig} from 'vite';

export default defineConfig(() => {
  return {
    plugins: [react(), tailwindcss()],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, '.'),
      },
    },
    server: {
      // HMR is disabled in AI Studio via DISABLE_HMR env var.
      // Do not modify—file watching is disabled to prevent flickering during agent edits.
      hmr: process.env.DISABLE_HMR !== 'true',
      // Disable file watching when DISABLE_HMR is true to save CPU during agent edits.
      watch: process.env.DISABLE_HMR === 'true' ? null : {},
    },
    build: {
      rollupOptions: {
        input: {
          main: path.resolve(__dirname, 'index.html'),
          components: path.resolve(__dirname, 'components.html'),
          directory: path.resolve(__dirname, 'directory.html'),
          discover: path.resolve(__dirname, 'discover.html'),
          discoverResults: path.resolve(__dirname, 'discover-results.html'),
          storeHome: path.resolve(__dirname, 'store/index.html'),
          storeProducts: path.resolve(__dirname, 'store/products.html'),
          storeProduct: path.resolve(__dirname, 'store/product.html'),
          storeCart: path.resolve(__dirname, 'store/cart.html'),
          storeCheckout: path.resolve(__dirname, 'store/checkout.html'),
          storeTrackOrder: path.resolve(__dirname, 'store/track-order.html'),
          storeEscrow: path.resolve(__dirname, 'store/escrow.html'),
          storeAbout: path.resolve(__dirname, 'store/about.html'),
          storeContact: path.resolve(__dirname, 'store/contact.html'),
          serviceHome: path.resolve(__dirname, 'service/index.html'),
          serviceMenu: path.resolve(__dirname, 'service/services.html'),
          serviceBook: path.resolve(__dirname, 'service/book.html'),
          serviceTrack: path.resolve(__dirname, 'service/track-booking.html'),
          serviceAbout: path.resolve(__dirname, 'service/about.html'),
          serviceContact: path.resolve(__dirname, 'service/contact.html'),
        },
      },
    },
  };
});
