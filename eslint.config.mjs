import js from "@eslint/js";
import globals from "globals";

export default [
    {
        ignores: [
            "node_modules/**",
            "public/build/**",
            "var/**",
            "vendor/**",
        ],
    },
    js.configs.recommended,
    {
        files: ["assets/**/*.js"],
        languageOptions: {
            ecmaVersion: "latest",
            sourceType: "module",
            globals: {
                ...globals.browser,
                // webpack provides a CommonJS-style `require` (incl. `require.context`) even in ESM-transpiled files
                require: "readonly",
            },
        },
        rules: {
            "no-unused-vars": ["warn", { args: "none" }],
        },
    },
];
