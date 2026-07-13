import { useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, StyleSheet, Text, TextInput, View } from 'react-native';
import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import { api } from '../api/client';
import { ensureSession } from '../api/auth';

const PAGE_SIZE = 20;
const ago = (date) => {
  const minutes = Math.max(1, Math.floor((Date.now() - new Date(date)) / 60000));
  return minutes < 60 ? `${minutes}m ago` : minutes < 1440 ? `${Math.floor(minutes / 60)}h ago` : `${Math.floor(minutes / 1440)}d ago`;
};

function PostCard({ post }) {
  const [reacted, setReacted] = useState(false);
  const initial = post.user?.name?.[0]?.toUpperCase() || '?';
  return <View style={styles.card}>
    <View style={styles.row}><View style={styles.avatar}><Text style={styles.avatarText}>{initial}</Text></View><View><Text style={styles.name}>{post.user?.name || 'GuisedUp member'}</Text><Text style={styles.time}>{ago(post.created_at)}</Text></View></View>
    <Text style={styles.content}>{post.content}</Text>
    <Pressable style={[styles.reaction, reacted && styles.reacted]} onPress={() => setReacted(!reacted)}><Text style={styles.reactionText}>{reacted ? '♥ Connected' : '♡ Connect'}</Text></Pressable>
  </View>;
}

export default function FeedScreen() {
  const [search, setSearch] = useState('');
  const feed = useInfiniteQuery({ queryKey: ['feed'], queryFn: async ({ pageParam = 1 }) => { await ensureSession(); return api.get('/feed', { params: { page: pageParam } }).then(r => r.data); }, initialPageParam: 1, getNextPageParam: last => last.current_page < last.last_page ? last.current_page + 1 : undefined });
  const results = useQuery({ queryKey: ['search', search], queryFn: async () => { await ensureSession(); return api.get('/search', { params: { q: search } }).then(r => r.data.data); }, enabled: search.trim().length > 1 });
  const isSearching = search.trim().length > 1;
  const posts = isSearching ? (results.data || []) : (feed.data?.pages.flatMap(page => page.data) || []);
  const isLoading = isSearching ? results.isLoading : feed.isLoading;
  const isError = isSearching ? results.isError : feed.isError;
  const retry = isSearching ? results.refetch : feed.refetch;
  if (isLoading) return <View style={styles.center}><ActivityIndicator size="large" color="#6047D9" /><Text style={styles.muted}>Finding real connections…</Text></View>;
  if (isError) return <View style={styles.center}><Text style={styles.error}>We could not load this content.</Text><Pressable onPress={retry}><Text style={styles.retry}>Try again</Text></Pressable></View>;
  return <View style={styles.screen}><Text style={styles.title}>GuisedUp</Text><Text style={styles.subtitle}>Real connections, ranked for you</Text><TextInput value={search} onChangeText={setSearch} placeholder="Search stories and people" style={styles.search} />
    <FlatList data={posts} keyExtractor={item => String(item.id)} renderItem={({ item }) => <PostCard post={item} />} contentContainerStyle={styles.list} refreshControl={<RefreshControl refreshing={feed.isRefetching} onRefresh={feed.refetch} tintColor="#6047D9" />} onEndReached={() => !search && feed.hasNextPage && feed.fetchNextPage()} onEndReachedThreshold={0.4} ListEmptyComponent={<View style={styles.center}><Text style={styles.muted}>{search ? 'No matching stories yet.' : 'Your feed is quiet. Check back soon.'}</Text></View>} ListFooterComponent={feed.isFetchingNextPage ? <ActivityIndicator color="#6047D9" /> : null} />
  </View>;
}

const styles = StyleSheet.create({ screen:{flex:1,backgroundColor:'#F8F8FC',paddingTop:64}, title:{fontSize:30,fontWeight:'800',color:'#201C36',paddingHorizontal:20},subtitle:{color:'#706C82',paddingHorizontal:20,marginTop:4,marginBottom:18},search:{marginHorizontal:20,marginBottom:8,backgroundColor:'#FFF',borderRadius:14,padding:14,fontSize:16,shadowColor:'#312A55',shadowOpacity:.05,shadowRadius:12,elevation:2},list:{padding:12,paddingBottom:40},card:{backgroundColor:'#FFF',borderRadius:18,padding:16,marginBottom:12,shadowColor:'#312A55',shadowOpacity:.06,shadowRadius:12,elevation:2},row:{flexDirection:'row',alignItems:'center'},avatar:{width:40,height:40,borderRadius:20,backgroundColor:'#E7E1FF',alignItems:'center',justifyContent:'center',marginRight:10},avatarText:{color:'#6047D9',fontWeight:'800'},name:{fontWeight:'700',color:'#29243E'},time:{fontSize:12,color:'#8B8798',marginTop:2},content:{fontSize:16,lineHeight:23,color:'#3E394C',marginTop:14},reaction:{alignSelf:'flex-start',marginTop:15,paddingVertical:8,paddingHorizontal:12,borderRadius:10,backgroundColor:'#F3F1FB'},reacted:{backgroundColor:'#EEE9FF'},reactionText:{color:'#6047D9',fontWeight:'700'},center:{flex:1,alignItems:'center',justifyContent:'center',padding:32},muted:{color:'#706C82',marginTop:12,textAlign:'center'},error:{color:'#B42318',fontSize:16},retry:{marginTop:12,color:'#6047D9',fontWeight:'700'} });
