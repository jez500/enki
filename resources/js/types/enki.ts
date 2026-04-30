export interface EnkiColor {
    bg: string;
    fg: string;
}

export interface EnkiCategory {
    id: string;
    label: string;
    icon: string | null;
    color: EnkiColor | null;
}

export interface EnkiTint {
    bg: string;
    fg: string;
}

export interface EnkiAuthor {
    name: string;
    members: number;
    skills: number;
}

export interface EnkiChangelogEntry {
    v: string;
    d: string;
    notes: string;
}

export interface EnkiActivityEntry {
    event: string;
    causer: string | null;
    at: string;
    atHuman: string;
}

export interface EnkiFile {
    path: string;
    size: string;
    kind: string;
    content: string | null;
}

export interface EnkiSkillSummary {
    slug: string;
    name: string;
    summary: string;
    categories: string[];
    author: string;
    version: string;
    updated: string;
    ratings: number;
    installs: number;
    tags: string[];
    starred: boolean;
    monogramTint: number;
    isExternal: boolean;
    githubUrl: string | null;
    importedBy: string | null;
    isPrivate: boolean;
    canEdit: boolean;
    canStar: boolean;
    categoryIcon: string | null;
    categoryColor: EnkiColor | null;
}

export interface EnkiSkill extends EnkiSkillSummary {
    readmeHtml: string;
    usageHtml: string;
    readmeFrontmatter: Record<string, string>;
    changelog: EnkiChangelogEntry[];
    activityLog: EnkiActivityEntry[];
    files: EnkiFile[];
}

export interface EnkiFilters {
    q: string;
    category: string;
    sort: string;
    starred: boolean;
    mySkills: boolean;
    source: 'all' | 'internal' | 'external';
}

export interface EnkiPrefs {
    scope: 'workspace' | 'project' | 'agent';
    autoUpdate: boolean;
    showExperimental: boolean;
    notify: boolean;
}
