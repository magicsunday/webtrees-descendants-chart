import resolve from '@rollup/plugin-node-resolve';
import terser from "@rollup/plugin-terser";

export default [
    // descendants-chart.js
    {
        input: "resources/js/modules/index.js",
        output: [
            {
                name: "WebtreesDescendantsChart",
                file: "resources/js/descendants-chart.js",
                format: "umd"
            }
        ],
        plugins: [
            resolve()
        ]
    },
    {
        input: "resources/js/modules/index.js",
        output: [
            {
                name: "WebtreesDescendantsChart",
                file: "resources/js/descendants-chart.min.js",
                format: "umd"
            }
        ],
        plugins: [
            resolve(),
            terser({
                mangle: true,
                compress: true,
                module: true,
                output: {
                    comments: false
                }
            })
        ]
    },

    // descendants-chart-storage.js
    {
        input: "resources/js/modules/storage.js",
        output: [
            {
                name: "WebtreesDescendantsChart",
                file: "resources/js/descendants-chart-storage.js",
                format: "umd"
            }
        ],
        plugins: [
            resolve()
        ]
    },
    {
        input: "resources/js/modules/storage.js",
        output: [
            {
                name: "WebtreesDescendantsChart",
                file: "resources/js/descendants-chart-storage.min.js",
                format: "umd"
            }
        ],
        plugins: [
            resolve(),
            terser({
                mangle: true,
                compress: true,
                module: true,
                output: {
                    comments: false
                }
            })
        ]
    }
];
