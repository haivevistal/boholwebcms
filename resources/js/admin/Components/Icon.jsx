import {
    Activity, Archive, BarChart3, Bell, Book, BookOpen, Box, Briefcase, Calendar, Camera, ChartPie, CircleHelp, Clipboard, Clock, Cloud,
    Code, Cog, CreditCard, Database, DollarSign, Download, Eraser, Eye, File, FileText, Film, Flag, Folder, Gift, Globe, GraduationCap,
    Hammer, Heart, HeartPulse, House, Image, Images, Inbox, Key, LayoutDashboard, LayoutGrid, LifeBuoy, Link, List, Lock, Mail, Map,
    MapPin, Megaphone, MessageCircle, MessageSquare, Mic, Music, Newspaper, Package, Paintbrush, Palette, Percent, Phone, PieChart, Pin,
    Plug, Puzzle, Receipt, Rocket, Search, Send, Server, Settings, Shield, ShoppingBag, ShoppingCart, SlidersHorizontal, Sparkles, Star,
    Store, Tag, Tags, Ticket, Truck, Upload, User, Users, Video, Wallet, Webhook, Wrench, Zap,
} from 'lucide-react';

/**
 * Menu icons. Plugins pass a name from this set (kebab-case, like
 * "shopping-cart"), an image URL, a data: URI, or raw "<svg…>" markup.
 * Extra names can be registered: CMS.registerIcon('rocket-2', MyComponent).
 */
export const ICONS = {
    activity: Activity, archive: Archive, 'bar-chart': BarChart3, bell: Bell, book: Book, 'book-open': BookOpen, box: Box,
    briefcase: Briefcase, calendar: Calendar, camera: Camera, 'chart-pie': ChartPie, help: CircleHelp, clipboard: Clipboard, clock: Clock,
    cloud: Cloud, code: Code, cog: Cog, 'credit-card': CreditCard, database: Database, dollar: DollarSign, download: Download, eraser: Eraser,
    eye: Eye, file: File, 'file-text': FileText, film: Film, flag: Flag, folder: Folder, gift: Gift, globe: Globe, 'graduation-cap': GraduationCap,
    hammer: Hammer, heart: Heart, 'heart-pulse': HeartPulse, home: House, house: House, image: Image, images: Images, inbox: Inbox, key: Key,
    'layout-dashboard': LayoutDashboard, 'layout-grid': LayoutGrid, 'life-buoy': LifeBuoy, link: Link, list: List, lock: Lock, mail: Mail,
    map: Map, 'map-pin': MapPin, megaphone: Megaphone, 'message-circle': MessageCircle, 'message-square': MessageSquare, mic: Mic,
    music: Music, newspaper: Newspaper, package: Package, paintbrush: Paintbrush, palette: Palette, percent: Percent, phone: Phone,
    'pie-chart': PieChart, pin: Pin, plug: Plug, puzzle: Puzzle, receipt: Receipt, rocket: Rocket, search: Search, send: Send, server: Server,
    settings: Settings, shield: Shield, 'shopping-bag': ShoppingBag, 'shopping-cart': ShoppingCart, sliders: SlidersHorizontal,
    sparkles: Sparkles, star: Star, store: Store, tag: Tag, tags: Tags, ticket: Ticket, truck: Truck, upload: Upload, user: User, users: Users,
    video: Video, wallet: Wallet, webhook: Webhook, wrench: Wrench, zap: Zap,
    // WordPress dashicon aliases for easy porting
    'dashicons-cart': ShoppingCart, 'dashicons-admin-generic': Settings, 'dashicons-admin-tools': Wrench, 'dashicons-chart-bar': BarChart3,
    'dashicons-admin-users': Users, 'dashicons-email': Mail, 'dashicons-calendar': Calendar, 'dashicons-store': Store, 'dashicons-format-gallery': Images,
};

export default function Icon({ name, className = 'size-4' }) {
    if (!name) return <Puzzle className={className} />;

    if (typeof name === 'string' && name.trim().startsWith('<svg')) {
        return <span className={className + ' inline-block [&>svg]:size-full'} dangerouslySetInnerHTML={{ __html: name }} />;
    }
    if (typeof name === 'string' && (name.startsWith('http') || name.startsWith('/') || name.startsWith('data:'))) {
        return <img src={name} alt="" className={className + ' object-contain'} />;
    }

    const Component = ICONS[name] || window.CMS?.icons?.[name] || Puzzle;
    return <Component className={className} />;
}
