import { defaultTheme } from "@vuepress/theme-default";

import { defineUserConfig } from "vuepress";
import { searchPlugin } from "@vuepress/plugin-search";
import { viteBundler } from "@vuepress/bundler-vite";

export default defineUserConfig({
	base: "/wordpress-search/",
	lang: "en-US",
	title: "WordPress Search Plugin",
	description: "Developer Documentation for WordPress Search Plugin",
	bundler: viteBundler({}),
	theme: defaultTheme({
		logo: "/images/BCID_H_rgb_pos.png",
		logoDark: "/images/BCID_H_rgb_rev.png",
		editLink: false,
		lastUpdated: false,
		repo: "bcgov/wordpress-search",
		repoLabel: "Github",
		sidebarDepth: 2,
		navbar: [
			{
				text: "Home",
				link: "/",
			},
		],
		sidebar: [
			{
				text: "Blocks",
				collapsible: true,
				children: [
					{ text: "Search", link: "/guide/Blocks/Search" },
					{ text: "Search Modal", link: "/guide/Blocks/SearchModal" },
					{
						text: "Search Post Filter",
						link: "/guide/Blocks/SearchPostFilter",
					},
					{
						text: "Search Taxonomy Filter",
						link: "/guide/Blocks/SearchTaxonomyFilter",
					},
					{
						text: "Search Results Post Metadata Display",
						link: "/guide/Blocks/SearchResultsPostMetadataDisplay",
					},
					{
						text: "Search Results Sort",
						link: "/guide/Blocks/SearchResultsSort",
					},
					{
						text: "Search Active Filters",
						link: "/guide/Blocks/SearchActiveFilters",
					},
					{
						text: "Search Result Count",
						link: "/guide/Blocks/SearchResultCount",
					},
				],
			},
			{
				text: "Developers",
				collapsible: true,
				children: [],
			},
		],
	}),
	plugins: [
		searchPlugin({
			locales: {
				"/": {
					placeholder: "Search...",
				},
			},
		}),
	],
});
