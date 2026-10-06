import { IconName } from '@/Components/Icon';

export interface NavItem {
    label: string;
    /** The named route the link opens. */
    route: string;
    /** Route-name pattern that marks the link as current, e.g. "students.*". */
    match: string;
    icon: IconName;
    /** Show the link only to users with at least one of these permissions. */
    permissions?: string[];
    /** Also require one of these roles, for school-wide settings that teachers may not change. */
    roles?: string[];
}

export interface NavGroup {
    label: string;
    items: NavItem[];
}

/**
 * The sidebar. Each module adds its links here. A link is shown only when
 * the user has one of its permissions; a group with no visible links is
 * left out. The server still checks every page and action.
 */
export const navigation: NavGroup[] = [
    {
        label: 'Overview',
        items: [
            { label: 'Dashboard', route: 'dashboard', match: 'dashboard', icon: 'home' },
            {
                label: 'Analytics',
                route: 'analytics',
                match: 'analytics',
                icon: 'chart',
                permissions: ['reports.view'],
                roles: ['super-admin', 'admin', 'teacher'],
            },
        ],
    },
    {
        label: 'People',
        items: [
            { label: 'Students', route: 'students.index', match: 'students.*', icon: 'student', permissions: ['students.view'] },
            { label: 'Teachers', route: 'teachers.index', match: 'teachers.*', icon: 'teacher', permissions: ['teachers.view'] },
        ],
    },
    {
        label: 'Academics',
        items: [
            {
                label: 'Academic years',
                route: 'academic-years.index',
                match: 'academic-years.*',
                icon: 'calendar',
                permissions: ['academic_years.view'],
            },
            { label: 'Classes', route: 'classes.index', match: 'classes.*', icon: 'class', permissions: ['classes.view'] },
            { label: 'Subjects', route: 'subjects.index', match: 'subjects.*', icon: 'book', permissions: ['subjects.view'] },
        ],
    },
    {
        label: 'Learning',
        items: [
            {
                label: 'Activities',
                route: 'activities.index',
                match: 'activities.*',
                icon: 'clipboard',
                permissions: ['assignments.view', 'examinations.view'],
            },
            { label: 'Grades', route: 'grades.index', match: 'grades.*', icon: 'chart', permissions: ['grades.view'] },
            {
                label: 'Attendance',
                route: 'attendance.index',
                match: 'attendance.*',
                icon: 'check',
                permissions: ['attendance.view'],
            },
            { label: 'Awards', route: 'awards.index', match: 'awards.*', icon: 'award', permissions: ['awards.view'] },
        ],
    },
    {
        label: 'Community',
        items: [
            { label: 'Forum', route: 'forum.index', match: 'forum.*', icon: 'chat', permissions: ['forum.view'] },
            { label: 'Help and FAQ', route: 'faqs.index', match: 'faqs.*', icon: 'help', permissions: ['faqs.view'] },
        ],
    },
    {
        label: 'Administration',
        items: [
            { label: 'Reports', route: 'reports.index', match: 'reports.*', icon: 'report', permissions: ['reports.view'] },
            { label: 'Users', route: 'users.index', match: 'users.*', icon: 'users', permissions: ['users.view'] },
            { label: 'Audit trail', route: 'audit.index', match: 'audit.*', icon: 'log', permissions: ['audit.view'] },
            {
                label: 'Grading',
                route: 'grading.index',
                match: 'grading.*',
                icon: 'settings',
                permissions: ['grades.manage'],
                roles: ['admin', 'super-admin'],
            },
        ],
    },
];
