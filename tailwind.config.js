import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";
import typography from "@tailwindcss/typography";

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: "class",
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./vendor/laravel/jetstream/**/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.js",
        // Clases armadas en PHP: badges de los enums y ->format() de las tablas
        // de rappasoft. Sin esta linea solo se compilaban si por casualidad
        // aparecian tambien en algun blade.
        "./app/**/*.php",
        //Datatables
        "./vendor/rappasoft/laravel-livewire-tables/resources/views/**/*.blade.php",
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ["Figtree", ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Verde salvia de la placa "Premium" del logo de Digital World.
                // Tiene nombre propio y no reutiliza green-*, porque green-* ya
                // significa "exito / disponible" en todo el sistema y la marca
                // no debe confundirse con un estado.
                brand: {
                    50: "#f3f6f1",
                    100: "#e3ebdf",
                    200: "#c8d7c0",
                    300: "#a5bd99",
                    400: "#7f9f71",
                    500: "#5f8352",
                    600: "#4a6a40",
                    700: "#3c5534",
                    800: "#32462c",
                    900: "#2a3a26",
                    950: "#151f13",
                },
                // Melocoton de la placa "Desert": acento secundario.
                desert: {
                    50: "#fcf8f3",
                    100: "#f8eee2",
                    200: "#f1dcc4",
                    300: "#e9cfae",
                    400: "#dcb07f",
                    500: "#d0955a",
                    600: "#c27c47",
                    700: "#a1623c",
                    800: "#825036",
                    900: "#69432f",
                },
            },
        },
    },

    plugins: [forms, typography],
};
