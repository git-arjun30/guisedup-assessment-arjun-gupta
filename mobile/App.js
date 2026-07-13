import { StatusBar } from 'expo-status-bar';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import FeedScreen from './src/screens/FeedScreen';

const client = new QueryClient();

export default function App() {
  return <QueryClientProvider client={client}><StatusBar style="dark" /><FeedScreen /></QueryClientProvider>;
}
